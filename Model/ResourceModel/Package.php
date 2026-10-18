<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Relation;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Persistence of starter package groups and their selectable products
 */
class Package
{
    public const GROUP_TABLE = 'netwrk_fixed_variant_group';
    public const ITEM_TABLE = 'netwrk_fixed_variant_item';

    /**
     * @param ResourceConnection $resource
     * @param Relation $relation
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Relation $relation,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * Load groups of a starter package, each with an ordered list of child product IDs
     *
     * @param int $parentId
     * @return array<int, array{group_id:int, title:string, qty:float, is_extra:bool, position:int, product_ids:int[]}>
     */
    public function getGroups(int $parentId): array
    {
        $connection = $this->resource->getConnection();
        $groups = [];
        $select = $connection->select()
            ->from($this->resource->getTableName(self::GROUP_TABLE))
            ->where('parent_id = ?', $parentId)
            ->order(['position ASC', 'group_id ASC']);
        foreach ($connection->fetchAll($select) as $row) {
            $groups[(int)$row['group_id']] = [
                'group_id' => (int)$row['group_id'],
                'title' => (string)$row['title'],
                'qty' => (float)$row['qty'],
                'is_extra' => (bool)$row['is_extra'],
                'position' => (int)$row['position'],
                'product_ids' => [],
            ];
        }
        if (!$groups) {
            return [];
        }
        $select = $connection->select()
            ->from($this->resource->getTableName(self::ITEM_TABLE), ['group_id', 'product_id'])
            ->where('group_id IN (?)', array_keys($groups))
            ->order(['position ASC', 'item_id ASC']);
        foreach ($connection->fetchAll($select) as $row) {
            $groups[(int)$row['group_id']]['product_ids'][] = (int)$row['product_id'];
        }
        return $groups;
    }

    /**
     * Replace all groups of a starter package
     *
     * @param int $parentId entity_id of the starter package
     * @param int $parentLinkId link field value (row_id on Adobe Commerce, entity_id on Open Source)
     * @param array $groups
     * @return void
     */
    public function saveGroups(int $parentId, int $parentLinkId, array $groups): void
    {
        $connection = $this->resource->getConnection();
        $groupTable = $this->resource->getTableName(self::GROUP_TABLE);
        $itemTable = $this->resource->getTableName(self::ITEM_TABLE);

        $connection->beginTransaction();
        try {
            $existing = array_map('intval', $connection->fetchCol(
                $connection->select()->from($groupTable, ['group_id'])->where('parent_id = ?', $parentId)
            ));
            $kept = [];
            $childIds = [];
            $position = 0;
            foreach ($groups as $group) {
                $row = [
                    'parent_id' => $parentId,
                    'title' => (string)$group['title'],
                    'qty' => max(0, (float)$group['qty']),
                    'is_extra' => empty($group['is_extra']) ? 0 : 1,
                    'position' => isset($group['position']) ? (int)$group['position'] : $position,
                ];
                // Keep group IDs stable: they are referenced by saved buy requests (wishlist, configure)
                $groupId = (int)($group['group_id'] ?? 0);
                if ($groupId && in_array($groupId, $existing, true) && !isset($kept[$groupId])) {
                    $connection->update($groupTable, $row, ['group_id = ?' => $groupId]);
                    $connection->delete($itemTable, ['group_id = ?' => $groupId]);
                } else {
                    $connection->insert($groupTable, $row);
                    $groupId = (int)$connection->lastInsertId($groupTable);
                }
                $kept[$groupId] = true;
                $rows = [];
                foreach (array_values(array_unique(array_map('intval', $group['product_ids'] ?? []))) as $i => $id) {
                    if ($id > 0 && $id !== $parentId) {
                        $rows[] = ['group_id' => $groupId, 'product_id' => $id, 'position' => $i];
                        $childIds[$id] = $id;
                    }
                }
                if ($rows) {
                    $connection->insertMultiple($itemTable, $rows);
                }
                $position++;
            }
            $removed = array_diff($existing, array_keys($kept));
            if ($removed) {
                $connection->delete($groupTable, ['group_id IN (?)' => $removed]);
            }
            // Keep catalog_product_relation in sync so indexers pick up the parent when a child changes
            $this->relation->processRelations($parentLinkId, array_values($childIds));
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * All child product IDs of a package, grouped by group ID
     *
     * @param int $parentId
     * @return array<int, array<int,int>>
     */
    public function getChildrenIds(int $parentId): array
    {
        $result = [];
        foreach ($this->getGroups($parentId) as $groupId => $group) {
            $result[$groupId] = array_combine($group['product_ids'], $group['product_ids']) ?: [];
        }
        return $result;
    }

    /**
     * Starter package (entity) IDs that contain one of the given children
     *
     * @param int|int[] $childIds
     * @return int[]
     */
    public function getParentIdsByChild($childIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(['g' => $this->resource->getTableName(self::GROUP_TABLE)], ['parent_id'])
            ->join(['i' => $this->resource->getTableName(self::ITEM_TABLE)], 'i.group_id = g.group_id', [])
            ->where('i.product_id IN (?)', (array)$childIds);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Summary per package for the admin overview
     *
     * @param int[] $parentIds
     * @return array<int, array{groups:int, pieces:float, extra_groups:int, products:int}>
     */
    public function getSummary(array $parentIds): array
    {
        if (!$parentIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['g' => $this->resource->getTableName(self::GROUP_TABLE)], [
                'parent_id',
                'groups' => new \Zend_Db_Expr('SUM(g.is_extra = 0)'),
                'extra_groups' => new \Zend_Db_Expr('SUM(g.is_extra = 1)'),
                'pieces' => new \Zend_Db_Expr('SUM(IF(g.is_extra = 0, g.qty, 0))'),
            ])
            ->where('g.parent_id IN (?)', $parentIds)
            ->group('g.parent_id');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['parent_id']] = [
                'groups' => (int)$row['groups'],
                'extra_groups' => (int)$row['extra_groups'],
                'pieces' => (float)$row['pieces'],
                'products' => 0,
            ];
        }
        $select = $connection->select()
            ->from(['g' => $this->resource->getTableName(self::GROUP_TABLE)], [
                'parent_id',
                'products' => new \Zend_Db_Expr('COUNT(DISTINCT i.product_id)'),
            ])
            ->join(['i' => $this->resource->getTableName(self::ITEM_TABLE)], 'i.group_id = g.group_id', [])
            ->where('g.parent_id IN (?)', $parentIds)
            ->group('g.parent_id');
        foreach ($connection->fetchPairs($select) as $parentId => $count) {
            if (isset($result[(int)$parentId])) {
                $result[(int)$parentId]['products'] = (int)$count;
            }
        }
        return $result;
    }

    /**
     * Link field name of the product entity (entity_id or row_id)
     *
     * @return string
     */
    public function getLinkField(): string
    {
        return $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }
}

<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Model\Indexer;

use Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;
use Netwrk\FixedVariantProduct\Model\ResourceModel\Package;

/**
 * Price index for starter packages.
 *
 * min_price / price / final_price: sum over the mandatory groups of (group qty x cheapest child)
 * max_price: sum over all groups (including extras) of (group qty x most expensive child)
 *
 * Children are indexed first (lower indexPriority), so their prices are read from the main index table,
 * the same way the grouped product indexer does it.
 */
class Price implements DimensionalIndexerInterface
{
    private ?AdapterInterface $connection = null;

    /**
     * @param IndexTableStructureFactory $indexTableStructureFactory
     * @param TableMaintainer $tableMaintainer
     * @param ResourceConnection $resource
     * @param string $connectionName
     * @param bool $fullReindexAction
     */
    public function __construct(
        private readonly IndexTableStructureFactory $indexTableStructureFactory,
        private readonly TableMaintainer $tableMaintainer,
        private readonly ResourceConnection $resource,
        private readonly string $connectionName = 'indexer',
        private readonly bool $fullReindexAction = false
    ) {
    }

    /**
     * @inheritdoc
     */
    public function executeByDimensions(array $dimensions, \Traversable $entityIds)
    {
        $temporaryPriceTable = $this->indexTableStructureFactory->create([
            'tableName' => $this->tableMaintainer->getMainTmpTable($dimensions),
            'entityField' => 'entity_id',
            'customerGroupField' => 'customer_group_id',
            'websiteField' => 'website_id',
            'taxClassField' => 'tax_class_id',
            'originalPriceField' => 'price',
            'finalPriceField' => 'final_price',
            'minPriceField' => 'min_price',
            'maxPriceField' => 'max_price',
            'tierPriceField' => 'tier_price',
        ]);
        $this->tableMaintainer->insertFromSelect(
            $this->getSelect($dimensions, iterator_to_array($entityIds)),
            $temporaryPriceTable->getTableName(),
            ['entity_id', 'customer_group_id', 'website_id', 'tax_class_id', 'price', 'final_price',
                'min_price', 'max_price', 'tier_price']
        );
    }

    /**
     * Build the select that aggregates child prices per group and then per package
     *
     * @param array $dimensions
     * @param array $entityIds
     * @return \Magento\Framework\DB\Select
     */
    private function getSelect(array $dimensions, array $entityIds): \Magento\Framework\DB\Select
    {
        $connection = $this->getConnection();
        $mainTable = $this->fullReindexAction
            ? $this->tableMaintainer->getMainReplicaTable($dimensions)
            : $this->tableMaintainer->getMainTableByDimensions($dimensions);

        // Per group: cheapest / most expensive child
        $perGroup = $connection->select()
            ->from(['g' => $this->getTable(Package::GROUP_TABLE)], ['g.group_id', 'g.parent_id', 'g.qty', 'g.is_extra'])
            ->join(['it' => $this->getTable(Package::ITEM_TABLE)], 'it.group_id = g.group_id', [])
            ->join(['i' => $mainTable], 'i.entity_id = it.product_id', [
                'i.customer_group_id',
                'i.website_id',
                'tax_class_id' => new \Zend_Db_Expr('MIN(i.tax_class_id)'),
                'min_child' => new \Zend_Db_Expr('MIN(i.min_price)'),
                'max_child' => new \Zend_Db_Expr('MAX(i.max_price)'),
            ])
            ->group(['g.group_id', 'i.customer_group_id', 'i.website_id']);
        if ($entityIds) {
            $perGroup->where('g.parent_id IN (?)', $entityIds, \Zend_Db::INT_TYPE);
        }

        $minExpr = new \Zend_Db_Expr('SUM(IF(pg.is_extra = 0, pg.qty * pg.min_child, 0))');
        $select = $connection->select()
            ->from(['e' => $this->getTable('catalog_product_entity')], ['entity_id'])
            ->join(['pg' => $perGroup], 'pg.parent_id = e.entity_id', [
                'pg.customer_group_id',
                'pg.website_id',
                'tax_class_id' => new \Zend_Db_Expr('MIN(pg.tax_class_id)'),
                'price' => $minExpr,
                'final_price' => $minExpr,
                'min_price' => $minExpr,
                'max_price' => new \Zend_Db_Expr('SUM(pg.qty * pg.max_child)'),
                'tier_price' => new \Zend_Db_Expr('NULL'),
            ])
            ->joinLeft(
                ['cgw' => $this->getTable('customer_group_excluded_website')],
                'pg.customer_group_id = cgw.customer_group_id AND pg.website_id = cgw.website_id',
                []
            )
            ->where('e.type_id = ?', FixedVariant::TYPE_CODE)
            ->where('cgw.website_id IS NULL')
            // A package is only priced for a customer group/website when every mandatory group has a price
            ->having(
                'SUM(pg.is_extra = 0) = (SELECT COUNT(*) FROM ' . $this->getTable(Package::GROUP_TABLE)
                . ' AS gc WHERE gc.parent_id = e.entity_id AND gc.is_extra = 0'
                . ' AND EXISTS (SELECT 1 FROM ' . $this->getTable(Package::ITEM_TABLE)
                . ' AS ic WHERE ic.group_id = gc.group_id))'
            )
            ->group(['e.entity_id', 'pg.customer_group_id', 'pg.website_id']);
        if ($entityIds) {
            $select->where('e.entity_id IN (?)', $entityIds, \Zend_Db::INT_TYPE);
        }
        return $select;
    }

    /**
     * @return AdapterInterface
     */
    private function getConnection(): AdapterInterface
    {
        if ($this->connection === null) {
            $this->connection = $this->resource->getConnection($this->connectionName);
        }
        return $this->connection;
    }

    /**
     * @param string $tableName
     * @return string
     */
    private function getTable(string $tableName): string
    {
        return $this->resource->getTableName($tableName, $this->connectionName);
    }
}

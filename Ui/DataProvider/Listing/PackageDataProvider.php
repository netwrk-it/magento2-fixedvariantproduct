<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Ui\DataProvider\Listing;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;
use Netwrk\FixedVariantProduct\Model\ResourceModel\Package;

/**
 * Product collection limited to starter packages, enriched with a summary of the groups
 */
class PackageDataProvider extends ProductDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param Package $package
     * @param array $addFieldStrategies
     * @param array $addFilterStrategies
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $modifiersPool
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly Package $package,
        array $addFieldStrategies = [],
        array $addFilterStrategies = [],
        array $meta = [],
        array $data = [],
        ?PoolInterface $modifiersPool = null
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $collectionFactory,
            $addFieldStrategies,
            $addFilterStrategies,
            $meta,
            $data,
            $modifiersPool
        );
        $this->collection->addAttributeToFilter('type_id', FixedVariant::TYPE_CODE);
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        $data = parent::getData();
        $ids = array_map(static fn($item) => (int)$item['entity_id'], $data['items']);
        $summary = $this->package->getSummary($ids);
        foreach ($data['items'] as &$item) {
            $row = $summary[(int)$item['entity_id']] ?? ['groups' => 0, 'extra_groups' => 0, 'pieces' => 0, 'products' => 0];
            $item['starter_groups'] = $row['groups'];
            $item['starter_extra_groups'] = $row['extra_groups'];
            $item['starter_pieces'] = rtrim(rtrim(number_format($row['pieces'], 2, '.', ''), '0'), '.');
            $item['starter_products'] = $row['products'];
        }
        return $data;
    }
}

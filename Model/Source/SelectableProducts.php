<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Model\Source;

use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Products that can be put in a starter package group (simple and virtual products)
 */
class SelectableProducts implements OptionSourceInterface
{
    private ?array $options = null;

    /**
     * @param CollectionFactory $collectionFactory
     * @param string[] $allowedTypes
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly array $allowedTypes = [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL]
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToSelect('name')
                ->addAttributeToFilter('type_id', ['in' => $this->allowedTypes])
                ->setOrder('name', 'ASC');
            $this->options = [];
            foreach ($collection as $product) {
                $this->options[] = [
                    'value' => (string)$product->getId(),
                    'label' => sprintf('%s (%s)', $product->getName(), $product->getSku()),
                ];
            }
        }
        return $this->options;
    }
}

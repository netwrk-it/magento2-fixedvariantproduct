<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Observer;

use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceProcessor;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;
use Netwrk\FixedVariantProduct\Model\ResourceModel\Package;

/**
 * Reindex the price of starter packages after a package or one of its products was saved
 */
class UpdateParentIndex implements ObserverInterface
{
    /**
     * @param Package $package
     * @param PriceProcessor $priceProcessor
     */
    public function __construct(
        private readonly Package $package,
        private readonly PriceProcessor $priceProcessor
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }
        $ids = $product->getTypeId() === FixedVariant::TYPE_CODE
            ? [(int)$product->getId()]
            : $this->package->getParentIdsByChild((int)$product->getId());
        if ($ids) {
            $this->priceProcessor->reindexList($ids);
        }
    }
}

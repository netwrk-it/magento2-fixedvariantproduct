<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Plugin;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * Copy "Pakket / Onderdeel" info of starter package lines to the order item
 */
class AdditionalOptionsToOrderItem
{
    /**
     * @param Json $serializer
     */
    public function __construct(
        private readonly Json $serializer
    ) {
    }

    /**
     * @param ToOrderItem $subject
     * @param OrderItemInterface $orderItem
     * @param AbstractItem $item
     * @param array $data
     * @return OrderItemInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterConvert(ToOrderItem $subject, $orderItem, AbstractItem $item, $data = [])
    {
        $typeOption = $item->getOptionByCode('product_type');
        $additional = $item->getOptionByCode('additional_options');
        if (!$additional || !$typeOption || $typeOption->getValue() !== FixedVariant::TYPE_CODE) {
            return $orderItem;
        }
        $options = $orderItem->getProductOptions() ?: [];
        $options['additional_options'] = $this->serializer->unserialize($additional->getValue());
        $orderItem->setProductOptions($options);
        return $orderItem;
    }
}

<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Plugin;

use Magento\Catalog\Model\Product\Type;
use Netwrk\FixedVariantProduct\Model\Config;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * Use the label from the configuration (default "Starter pakket") for the product type
 */
class ProductTypeLabel
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param Type $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetTypes(Type $subject, $result)
    {
        if (isset($result[FixedVariant::TYPE_CODE])) {
            $result[FixedVariant::TYPE_CODE]['label'] = $this->config->getTypeLabel();
        }
        return $result;
    }

    /**
     * Option arrays are built from getTypes() but some callers read labels directly
     *
     * @param Type $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetOptionArray(Type $subject, $result)
    {
        if (isset($result[FixedVariant::TYPE_CODE])) {
            $result[FixedVariant::TYPE_CODE] = $this->config->getTypeLabel();
        }
        return $result;
    }
}

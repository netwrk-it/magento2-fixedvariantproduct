<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Pricing\Price;

use Magento\Catalog\Pricing\Price\FinalPrice as CatalogFinalPrice;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * Final price of a starter package = cheapest possible composition of the mandatory groups
 */
class FinalPrice extends CatalogFinalPrice
{
    /**
     * @inheritdoc
     */
    public function getValue()
    {
        if ($this->value === null) {
            $minimal = $this->product->getData('minimal_price');
            if ($minimal !== null && $minimal !== '') {
                // Listing collections already carry the indexed price
                $this->value = (float)$minimal;
            } else {
                $type = $this->product->getTypeInstance();
                $this->value = $type instanceof FixedVariant ? $type->getMinimalPrice($this->product) : 0.0;
            }
        }
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function getMinimalPrice()
    {
        return $this->calculator->getAmount($this->getValue(), $this->product);
    }

    /**
     * @inheritdoc
     */
    public function getMaximalPrice()
    {
        return $this->getMinimalPrice();
    }
}

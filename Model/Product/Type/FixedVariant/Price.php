<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

use Magento\Catalog\Model\Product;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * The package is never ordered itself; its "price" is the cheapest possible composition.
 */
class Price extends Product\Type\Price
{
    /**
     * @inheritdoc
     */
    public function getPrice($product)
    {
        $type = $product->getTypeInstance();
        return $type instanceof FixedVariant ? $type->getMinimalPrice($product) : parent::getPrice($product);
    }

    /**
     * @inheritdoc
     */
    public function getFinalPrice($qty, $product)
    {
        if ($qty === null && $product->getCalculatedFinalPrice() !== null) {
            return $product->getCalculatedFinalPrice();
        }
        $product->setFinalPrice($this->getPrice($product));
        return max(0, (float)$product->getData('final_price'));
    }
}

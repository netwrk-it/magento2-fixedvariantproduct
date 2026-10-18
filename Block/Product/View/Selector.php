<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Image;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Netwrk\FixedVariantProduct\Model\Config;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * Product page: let the customer compose the package
 */
class Selector extends AbstractProduct
{
    /**
     * @param Context $context
     * @param Json $json
     * @param FormatInterface $localeFormat
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly FormatInterface $localeFormat,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Groups with products, ready for the template
     *
     * @return array
     */
    public function getGroups(): array
    {
        $product = $this->getProduct();
        $type = $product->getTypeInstance();
        if (!$type instanceof FixedVariant) {
            return [];
        }
        $preset = $this->getPreset();
        $groups = [];
        foreach ($type->getGroupsWithProducts($product) as $groupId => $group) {
            $items = [];
            foreach ($group['products'] as $child) {
                $price = $child->getPriceInfo()->getPrice('final_price')->getAmount();
                $items[] = [
                    'id' => (int)$child->getId(),
                    'name' => (string)$child->getName(),
                    'sku' => (string)$child->getSku(),
                    'salable' => (bool)$child->isSalable(),
                    'price' => (float)$price->getValue(),
                    'image' => $this->_imageHelper->init($child, 'product_thumbnail_image')->getUrl(),
                    'qty' => (float)($preset[$groupId][$child->getId()] ?? 0),
                ];
            }
            if (!$items) {
                continue;
            }
            // Nothing to choose: pre-fill a mandatory group that has a single product (e.g. the pattern)
            if (!$preset && !$group['is_extra'] && count($items) === 1 && $items[0]['salable']) {
                $items[0]['qty'] = (float)$group['qty'];
            }
            $groups[] = [
                'id' => (int)$groupId,
                'title' => $group['title'],
                'qty' => (float)$group['qty'],
                'is_extra' => (bool)$group['is_extra'],
                'items' => $items,
            ];
        }
        return $groups;
    }

    /**
     * Selection when editing a configured item (e.g. from the wishlist)
     *
     * @return array
     */
    private function getPreset(): array
    {
        $preconfigured = $this->getProduct()->getPreconfiguredValues();
        $value = $preconfigured ? $preconfigured->getData(FixedVariant::BUY_REQUEST_KEY) : null;
        return is_array($value) ? $value : [];
    }

    /**
     * Image helper
     *
     * @return Image
     */
    public function getImageHelper(): Image
    {
        return $this->_imageHelper;
    }

    /**
     * Config for the JS widget
     *
     * @return string
     */
    public function getJsonConfig(): string
    {
        return $this->json->serialize([
            'priceFormat' => $this->localeFormat->getPriceFormat(),
            'inputName' => FixedVariant::BUY_REQUEST_KEY,
        ]);
    }

    /**
     * @return Config
     */
    public function getModuleConfig(): Config
    {
        return $this->config;
    }

    /**
     * Format a quantity without useless decimals
     *
     * @param float $qty
     * @return string
     */
    public function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }
}

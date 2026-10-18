<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Access to Stores > Configuration > Netwrk > Starter pakket
 */
class Config
{
    private const XML_PATH = 'netwrk_fixed_variant/general/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Admin label of the product type
     *
     * @return string
     */
    public function getTypeLabel(): string
    {
        $label = trim((string)$this->scopeConfig->getValue(self::XML_PATH . 'type_label'));
        return $label !== '' ? $label : 'Starter pakket';
    }

    /**
     * Frontend label of the "order extra material" toggle
     *
     * @return string
     */
    public function getExtraLabel(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH . 'extra_label', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Frontend explanation below the extra material toggle
     *
     * @return string
     */
    public function getExtraNote(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH . 'extra_note', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Label in front of the price in listings
     *
     * @return string
     */
    public function getPriceLabel(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH . 'price_label', ScopeInterface::SCOPE_STORE);
    }
}

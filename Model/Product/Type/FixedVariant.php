<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Model\Product\Type;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Netwrk\FixedVariantProduct\Model\ResourceModel\Package as PackageResource;

/**
 * "Starter pakket" product type.
 *
 * Works like a grouped product: the package itself is never ordered, the products the customer picks are
 * added to the cart as separate lines. The admin defines groups with a fixed total quantity (e.g. 10x wool);
 * the customer distributes that quantity over the products of the group (e.g. 6x yellow + 4x red).
 * Groups flagged as "extra" are optional and the quantity is a maximum (spare material for beginners).
 */
class FixedVariant extends Product\Type\AbstractType
{
    public const TYPE_CODE = 'fixed_variant';

    /**
     * Buy request key: [group_id => [product_id => qty]]
     */
    public const BUY_REQUEST_KEY = 'fixed_variant';

    private const KEY_GROUPS = '_cache_instance_fixed_variant_groups';
    private const KEY_PRODUCTS = '_cache_instance_fixed_variant_products';

    /**
     * @param Product\Option $catalogProductOption
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param Product\Type $catalogProductType
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Psr\Log\LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param PackageResource $packageResource
     * @param CollectionFactory $productCollectionFactory
     * @param Json|null $serializer
     */
    public function __construct(
        Product\Option $catalogProductOption,
        \Magento\Eav\Model\Config $eavConfig,
        Product\Type $catalogProductType,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Registry $coreRegistry,
        \Psr\Log\LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        private readonly PackageResource $packageResource,
        private readonly CollectionFactory $productCollectionFactory,
        ?Json $serializer = null
    ) {
        parent::__construct(
            $catalogProductOption,
            $eavConfig,
            $catalogProductType,
            $eventManager,
            $fileStorageDb,
            $filesystem,
            $coreRegistry,
            $logger,
            $productRepository,
            $serializer
        );
    }

    /**
     * @inheritdoc
     */
    public function getRelationInfo()
    {
        $info = new DataObject();
        $info->setTable('catalog_product_relation')
            ->setParentFieldName('parent_id')
            ->setChildFieldName('child_id');
        return $info;
    }

    /**
     * @inheritdoc
     */
    public function getChildrenIds($parentId, $required = true)
    {
        return $this->packageResource->getChildrenIds((int)$parentId);
    }

    /**
     * @inheritdoc
     */
    public function getParentIdsByChild($childId)
    {
        return $this->packageResource->getParentIdsByChild($childId);
    }

    /**
     * Groups configured on the package (cached on the product instance)
     *
     * @param Product $product
     * @return array
     */
    public function getGroups($product): array
    {
        if (!$product->hasData(self::KEY_GROUPS)) {
            $groups = $product->getId() ? $this->packageResource->getGroups((int)$product->getId()) : [];
            $product->setData(self::KEY_GROUPS, $groups);
        }
        return $product->getData(self::KEY_GROUPS);
    }

    /**
     * All selectable products of the package, keyed by ID
     *
     * @param Product $product
     * @return Product[]
     */
    public function getChildProducts($product): array
    {
        if (!$product->hasData(self::KEY_PRODUCTS)) {
            $ids = [];
            foreach ($this->getGroups($product) as $group) {
                $ids = array_merge($ids, $group['product_ids']);
            }
            $products = [];
            if ($ids) {
                /** @var Collection $collection */
                $collection = $this->productCollectionFactory->create();
                $collection->addIdFilter(array_unique($ids))
                    ->addAttributeToSelect(['name', 'price', 'special_price', 'special_from_date',
                        'special_to_date', 'tax_class_id', 'small_image', 'thumbnail', 'image', 'status'])
                    ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                    ->addStoreFilter($this->getStoreFilter($product) ?? $product->getStoreId());
                if ($product->getStoreId() !== null) {
                    $collection->setStoreId($product->getStoreId());
                }
                $collection->addPriceData();
                foreach ($collection as $child) {
                    $products[(int)$child->getId()] = $child;
                }
            }
            $product->setData(self::KEY_PRODUCTS, $products);
        }
        return $product->getData(self::KEY_PRODUCTS);
    }

    /**
     * Groups with their (enabled) products resolved
     *
     * @param Product $product
     * @return array
     */
    public function getGroupsWithProducts($product): array
    {
        $children = $this->getChildProducts($product);
        $result = [];
        foreach ($this->getGroups($product) as $groupId => $group) {
            $group['products'] = [];
            foreach ($group['product_ids'] as $id) {
                if (isset($children[$id])) {
                    $group['products'][$id] = $children[$id];
                }
            }
            $result[$groupId] = $group;
        }
        return $result;
    }

    /**
     * Used by AbstractType::_prepareProduct() of the children to validate super_product_config
     *
     * @param Product $product
     * @return int[]
     */
    public function getAssociatedProductIds($product)
    {
        return array_keys($this->getChildProducts($product));
    }

    /**
     * @inheritdoc
     */
    public function getAssociatedProducts($product)
    {
        return array_values($this->getChildProducts($product));
    }

    /**
     * @inheritdoc
     */
    public function isSalable($product)
    {
        if (!parent::isSalable($product)) {
            return false;
        }
        // Every mandatory group needs at least one salable product
        foreach ($this->getGroupsWithProducts($product) as $group) {
            if ($group['is_extra'] || $group['qty'] <= 0) {
                continue;
            }
            $hasSalable = false;
            foreach ($group['products'] as $child) {
                if ($child->isSalable()) {
                    $hasSalable = true;
                    break;
                }
            }
            if (!$hasSalable) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isComposite($product)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canConfigure($product)
    {
        return true;
    }

    /**
     * The selection is rendered in product.info.form.content (like grouped), which Luma only shows
     * for products without options; hasRequiredOptions() still forces a visit to the product page.
     *
     * @inheritdoc
     */
    public function hasOptions($product)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasRequiredOptions($product)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isPossibleBuyFromList($product)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasWeight()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isVirtual($product)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteTypeSpecificData(Product $product)
    {
        // Groups are removed by the foreign key cascade on catalog_product_entity
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($product)
    {
        $product->unsetData(self::KEY_GROUPS);
        $product->unsetData(self::KEY_PRODUCTS);
        return parent::beforeSave($product);
    }

    /**
     * Persist groups that were posted from the admin form or set through code
     *
     * @param Product $product
     * @return $this
     */
    public function save($product)
    {
        parent::save($product);
        $groups = $product->getData('fixed_variant_groups');
        if (is_array($groups)) {
            $linkField = $this->packageResource->getLinkField();
            $this->packageResource->saveGroups(
                (int)$product->getId(),
                (int)($product->getData($linkField) ?: $product->getId()),
                $groups
            );
            $product->unsetData('fixed_variant_groups');
            $product->unsetData(self::KEY_GROUPS);
            $product->unsetData(self::KEY_PRODUCTS);
        }
        return $this;
    }

    /**
     * Validate the customer's selection and turn it into separate cart lines
     *
     * @param DataObject $buyRequest
     * @param Product $product
     * @param string $processMode
     * @return array|string
     */
    protected function _prepareProduct(DataObject $buyRequest, $product, $processMode)
    {
        $isStrict = $this->_isStrictProcessMode($processMode);
        $selection = $this->normalizeSelection($buyRequest->getData(self::BUY_REQUEST_KEY));

        if (!$isStrict) {
            // Wishlist / compare etc.: keep the package itself with the chosen configuration
            $product->addCustomOption('product_type', self::TYPE_CODE, $product);
            $product->addCustomOption('info_buyRequest', $this->serializer->serialize($buyRequest->getData()));
            return [$product];
        }

        try {
            $this->validateSelection($product, $selection);
        } catch (LocalizedException $e) {
            return $e->getMessage();
        }

        $sets = (float)$buyRequest->getQty();
        if ($sets <= 0) {
            $sets = 1;
        }

        $groups = $this->getGroupsWithProducts($product);
        $result = [];
        foreach ($selection as $groupId => $items) {
            $group = $groups[$groupId];
            foreach ($items as $childId => $qty) {
                // Clone: the same product can be picked in more than one group
                $child = clone $group['products'][$childId];
                $childRequest = new DataObject([
                    'qty' => $qty * $sets,
                    'product' => $child->getId(),
                    'super_product_config' => [
                        'product_type' => self::TYPE_CODE,
                        'product_id' => $product->getId(),
                    ],
                ]);
                $prepared = $child->getTypeInstance()->_prepareProduct($childRequest, $child, $processMode);
                if (is_string($prepared)) {
                    return $prepared;
                }
                if (!isset($prepared[0])) {
                    return __('Cannot process the item.')->render();
                }
                $prepared[0]->setCartQty($qty * $sets);
                $prepared[0]->addCustomOption(
                    'additional_options',
                    $this->serializer->serialize([
                        ['label' => (string)__('Package'), 'value' => (string)$product->getName()],
                        ['label' => (string)__('Part'), 'value' => $group['title']
                            . ($group['is_extra'] ? ' (' . __('extra') . ')' : '')],
                    ])
                );
                $result[] = $prepared[0];
            }
        }

        if (!$result) {
            return __('Please make your selection for the package.')->render();
        }
        return $result;
    }

    /**
     * Check the selection against the configured groups
     *
     * @param Product $product
     * @param array $selection
     * @return void
     * @throws LocalizedException
     */
    public function validateSelection($product, array $selection): void
    {
        $groups = $this->getGroupsWithProducts($product);
        if (!$groups) {
            throw new LocalizedException(__('This package has not been configured yet.'));
        }
        foreach ($selection as $groupId => $items) {
            if (!isset($groups[$groupId])) {
                throw new LocalizedException(__('The selected option is not available.'));
            }
            foreach (array_keys($items) as $childId) {
                $child = $groups[$groupId]['products'][$childId] ?? null;
                if (!$child) {
                    throw new LocalizedException(__('The selected product is not available.'));
                }
                if (!$child->isSalable()) {
                    throw new LocalizedException(
                        __('Product "%1" is currently not available.', $child->getName())
                    );
                }
            }
        }
        foreach ($groups as $groupId => $group) {
            $total = array_sum($selection[$groupId] ?? []);
            if ($group['is_extra']) {
                if ($total > $group['qty']) {
                    throw new LocalizedException(
                        __('You can choose at most %1 pieces for "%2".', $this->formatQty($group['qty']), $group['title'])
                    );
                }
                continue;
            }
            if (abs($total - $group['qty']) > 0.0001) {
                throw new LocalizedException(
                    __(
                        'Please choose exactly %1 pieces for "%2" (now %3).',
                        $this->formatQty($group['qty']),
                        $group['title'],
                        $this->formatQty($total)
                    )
                );
            }
        }
    }

    /**
     * Turn the posted selection into [group_id => [product_id => qty]] without empty entries
     *
     * @param mixed $raw
     * @return array
     */
    private function normalizeSelection($raw): array
    {
        $selection = [];
        if (!is_array($raw)) {
            return $selection;
        }
        foreach ($raw as $groupId => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $childId => $qty) {
                $qty = (float)$qty;
                if ($qty > 0) {
                    $selection[(int)$groupId][(int)$childId] = $qty;
                }
            }
        }
        return $selection;
    }

    /**
     * Format a quantity without useless decimals
     *
     * @param float $qty
     * @return string
     */
    private function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * @inheritdoc
     */
    public function processBuyRequest($product, $buyRequest)
    {
        $selection = $buyRequest->getData(self::BUY_REQUEST_KEY);
        return [self::BUY_REQUEST_KEY => is_array($selection) ? $selection : []];
    }

    /**
     * @inheritdoc
     */
    public function getSpecifyOptionMessage()
    {
        return __('Please make your selection for the package.');
    }

    /**
     * Cheapest possible total of the mandatory groups
     *
     * @param Product $product
     * @return float
     */
    public function getMinimalPrice($product): float
    {
        $total = 0.0;
        foreach ($this->getGroupsWithProducts($product) as $group) {
            if ($group['is_extra'] || !$group['products']) {
                continue;
            }
            $min = null;
            foreach ($group['products'] as $child) {
                $price = (float)$child->getPriceInfo()->getPrice('final_price')->getValue();
                $min = $min === null ? $price : min($min, $price);
            }
            $total += $min * $group['qty'];
        }
        return $total;
    }
}

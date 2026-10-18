<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Plugin;

use Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * Pass the groups posted from the product form to the product, saved in FixedVariant::save()
 */
class InitializeFixedVariant
{
    /**
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @param Helper $subject
     * @param Product $product
     * @return Product
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterInitialize(Helper $subject, Product $product)
    {
        if ($product->getTypeId() !== FixedVariant::TYPE_CODE) {
            return $product;
        }
        $post = $this->request->getPost('fixed_variant');
        if (!is_array($post) || empty($post['is_posted'])) {
            return $product;
        }
        $groups = [];
        foreach ($post['groups'] ?? [] as $row) {
            if (!is_array($row) || !empty($row['is_delete'])) {
                continue;
            }
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $productIds = $row['product_ids'] ?? [];
            if (!is_array($productIds)) {
                $productIds = array_filter(explode(',', (string)$productIds));
            }
            $groups[] = [
                'group_id' => (int)($row['group_id'] ?? 0),
                'title' => $title,
                'qty' => (float)($row['qty'] ?? 0),
                'is_extra' => !empty($row['is_extra']),
                'position' => (int)($row['position'] ?? count($groups)),
                'product_ids' => array_map('intval', $productIds),
            ];
        }
        usort($groups, static fn($a, $b) => $a['position'] <=> $b['position']);
        $product->setData('fixed_variant_groups', $groups);
        return $product;
    }
}

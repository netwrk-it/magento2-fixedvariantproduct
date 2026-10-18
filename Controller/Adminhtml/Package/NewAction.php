<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Controller\Adminhtml\Package;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant;

/**
 * "Nieuw starter pakket" button: forward to the regular product form with the right type
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    /**
     * @param Action\Context $context
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Action\Context $context,
        private readonly ProductFactory $productFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        return $this->resultRedirectFactory->create()->setPath('catalog/product/new', [
            'set' => $this->productFactory->create()->getDefaultAttributeSetId(),
            'type' => FixedVariant::TYPE_CODE,
        ]);
    }
}

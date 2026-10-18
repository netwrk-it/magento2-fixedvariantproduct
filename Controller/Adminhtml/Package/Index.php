<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Controller\Adminhtml\Package;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Netwrk\FixedVariantProduct\Model\Config;

/**
 * Content > Netwrk > Starter pakketten
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Netwrk_FixedVariantProduct::packages';

    /**
     * @param Action\Context $context
     * @param PageFactory $resultPageFactory
     * @param Config $config
     */
    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Netwrk_FixedVariantProduct::packages');
        $page->getConfig()->getTitle()->prepend($this->config->getTypeLabel() . ' — ' . __('Overview'));
        return $page;
    }
}

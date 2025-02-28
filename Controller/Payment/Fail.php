<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;

/**
 * Monei payment fail controller
 */
class Fail implements ActionInterface
{
    /**
     * @var OrderInterfaceFactory
     */
    protected $orderFactory;
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var SetOrderStatusAndStateInterface
     */
    private $setOrderStatusAndStateService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderInterfaceFactory $orderFactory
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService
     * @param ManagerInterface $messageManager
     * @param MagentoRedirect $resultRedirectFactory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderInterfaceFactory $orderFactory,
        SetOrderStatusAndStateInterface $setOrderStatusAndStateService,
        ManagerInterface $messageManager,
        MagentoRedirect $resultRedirectFactory
    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->setOrderStatusAndStateService = $setOrderStatusAndStateService;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $data = $this->context->getRequest()->getParams();
        if (isset($data['orderId'])) {
            $this->setOrderStatusAndStateService->execute($data);
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(
                __('Something went wrong while processing the payment. Quote was restored.')
            );
        } else {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while processing the payment. Cannot restore previous quote.')
            );
        }

        return $this->resultRedirectFactory->setPath('checkout/cart', ['_secure' => true]);
    }
}

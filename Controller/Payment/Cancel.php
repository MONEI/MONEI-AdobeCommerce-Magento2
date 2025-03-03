<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Monei payment cancel controller.
 */
class Cancel implements ActionInterface
{
    /** @var Session */
    private $checkoutSession;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var Context */
    private $context;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var MagentoRedirect */
    private $resultRedirectFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param MagentoRedirect $resultRedirectFactory
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        MagentoRedirect $resultRedirectFactory
    ) {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * Execute action to cancel payment and restore quote
     *
     * @return MagentoRedirect
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $order->cancel();
        $this->orderRepository->save($order);
        $this->checkoutSession->restoreQuote();
        $this->messageManager->addNoticeMessage(
            __('Payment was canceled. Quote was restored.')
        );

        return $this->resultRedirectFactory->setPath('checkout/cart');
    }
}

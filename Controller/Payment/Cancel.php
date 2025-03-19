<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Monei payment cancel controller.
 * Implements HttpGetActionInterface to specify it handles GET requests
 */
class Cancel implements HttpGetActionInterface
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var MagentoRedirect
     */
    private MagentoRedirect $resultRedirectFactory;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * Constructor.
     *
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param MagentoRedirect $resultRedirectFactory
     * @param HttpRequest $request
     */
    public function __construct(
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        MagentoRedirect $resultRedirectFactory,
        HttpRequest $request
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->request = $request;
    }

    /**
     * Execute action to cancel payment and restore quote.
     * This controller handles GET requests for payment cancellation
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

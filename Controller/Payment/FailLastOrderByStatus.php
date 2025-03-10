<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Monei payment fail insite controller.
 * Extends Fail to handle cases where the order ID needs to be retrieved from the session
 */
class FailLastOrderByStatus extends Fail
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;

    /**
     * @var RedirectFactory
     */
    protected RedirectFactory $resultRedirectFactory;

    /**
     * Constructor for FailLastOrderByStatus controller.
     *
     * @param Session $checkoutSession Checkout session
     * @param OrderFactory $orderFactory Order factory
     * @param ManagerInterface $messageManager Message manager
     * @param RedirectFactory $resultRedirectFactory Redirect factory
     * @param PaymentProcessorInterface $paymentProcessor Payment processor
     * @param Logger $logger Logger
     * @param HttpRequest $request HTTP request
     */
    public function __construct(
        Session $checkoutSession,
        OrderFactory $orderFactory,
        ManagerInterface $messageManager,
        RedirectFactory $resultRedirectFactory,
        PaymentProcessorInterface $paymentProcessor,
        Logger $logger,
        HttpRequest $request
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;

        parent::__construct(
            $checkoutSession,
            $orderFactory,
            $messageManager,
            $resultRedirectFactory,
            $paymentProcessor,
            $logger,
            $request
        );
    }

    /**
     * Execute the failure handling for the last real order.
     *
     * Takes the last real order from the checkout session and sets its
     * order ID in the request parameters before delegating to the parent
     * execute method for standard failure processing.
     *
     * @return MagentoRedirect Redirect to the appropriate page after failure handling
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Check if we have a valid order
        if (!$order || !$order->getIncrementId()) {
            $this->logger->error('[FailLastOrderByStatus] No valid order found in session');
            $this->messageManager->addErrorMessage(
                __('Something went wrong while processing the payment. Unable to restore your cart.')
            );

            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $params = $this->request->getParams();
        $params['orderId'] = $order->getIncrementId();

        // Ensure we have a payment ID, even if it's unknown
        if (!isset($params['paymentId']) && !isset($params['id'])) {
            $params['id'] = 'unknown';
        }

        $this->request->setParams($params);

        return parent::execute();
    }
}

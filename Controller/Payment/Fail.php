<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Logger;

/**
 * Controller for handling payment failure redirects from MONEI
 * Implements HttpGetActionInterface to specify it handles GET requests
 */
class Fail implements HttpGetActionInterface
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var RedirectFactory
     */
    private RedirectFactory $resultRedirectFactory;

    /**
     * @var PaymentProcessorInterface
     */
    private PaymentProcessorInterface $paymentProcessor;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * Constructor for Fail controller
     *
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $resultRedirectFactory
     * @param PaymentProcessorInterface $paymentProcessor
     * @param Logger $logger
     * @param HttpRequest $request
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
        $this->orderFactory = $orderFactory;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * Process payment failure redirect from MONEI
     * This controller handles GET requests from payment gateway redirects
     *
     * @return Redirect
     */
    public function execute()
    {
        try {
            $params = $this->request->getParams();
            $this->logger->debug('---------------------------------------------');
            $this->logger->debug('[Fail] Payment failure received', [
                'order_id' => $params['orderId'] ?? 'unknown',
                'payment_id' => $params['paymentId'] ?? $params['id'] ?? 'unknown',
                'status' => $params['status'] ?? 'unknown'
            ]);

            if (isset($params['orderId'])) {
                $orderId = $params['orderId'];
                $paymentId = $params['paymentId'] ?? $params['id'] ?? 'unknown';

                try {
                    // Prepare payment data for failed payment
                    $paymentData = [
                        'id' => $paymentId,
                        'orderId' => $orderId,
                        'status' => $params['status'] ?? Status::FAILED,
                        'amount' => $params['amount'] ?? 0,
                        'currency' => $params['currency'] ?? 'EUR'
                    ];

                    // Create a PaymentDTO instance
                    $paymentDTO = PaymentDTO::fromArray($paymentData);

                    // Process the failed payment through the standard processor
                    $result = $this->paymentProcessor->process(
                        $paymentDTO->getOrderId(),
                        $paymentDTO->getId(),
                        $paymentDTO->getRawData()
                    );

                    $this->logger->debug('[Fail] Payment processing result', [
                        'success' => $result->isSuccess(),
                        'message' => $result->getMessage()
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('[Fail] Error processing failed payment', [
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'error' => $e->getMessage()
                    ]);
                }

                // Restore the quote for the customer to try again
                $this->checkoutSession->restoreQuote();
                $this->messageManager->addErrorMessage(
                    __('Something went wrong while processing the payment. Your cart has been restored.')
                );
            } else {
                $this->logger->error('[Fail] Missing order ID in payment failure data');
                $this->messageManager->addErrorMessage(
                    __('Something went wrong while processing the payment. Unable to restore your cart.')
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('[Fail] Unhandled exception', [
                'error' => $e->getMessage()
            ]);
            $this->messageManager->addErrorMessage(
                __('An error occurred during payment processing. Please try again.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }
}

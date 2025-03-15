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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Logger;

/**
 * Unified controller for handling all payment redirects from MONEI
 * Handles both successful and failed payment scenarios
 * Implements HttpGetActionInterface to specify it handles GET requests
 */
class Complete implements HttpGetActionInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var RedirectFactory
     */
    private RedirectFactory $resultRedirectFactory;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var PaymentProcessorInterface
     */
    private PaymentProcessorInterface $paymentProcessor;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var GetPaymentInterface
     */
    private GetPaymentInterface $getPaymentService;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var PaymentDTOFactory
     */
    private PaymentDTOFactory $paymentDtoFactory;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RedirectFactory $resultRedirectFactory
     * @param Logger $logger
     * @param PaymentProcessorInterface $paymentProcessor
     * @param MoneiApiClient $apiClient
     * @param GetPaymentInterface $getPaymentService
     * @param HttpRequest $request
     * @param Session $checkoutSession
     * @param ManagerInterface $messageManager
     * @param OrderFactory $orderFactory
     * @param PaymentDTOFactory $paymentDtoFactory
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        RedirectFactory $resultRedirectFactory,
        Logger $logger,
        PaymentProcessorInterface $paymentProcessor,
        MoneiApiClient $apiClient,
        GetPaymentInterface $getPaymentService,
        HttpRequest $request,
        Session $checkoutSession,
        ManagerInterface $messageManager,
        OrderFactory $orderFactory,
        PaymentDTOFactory $paymentDtoFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->logger = $logger;
        $this->paymentProcessor = $paymentProcessor;
        $this->apiClient = $apiClient;
        $this->getPaymentService = $getPaymentService;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->orderFactory = $orderFactory;
        $this->paymentDtoFactory = $paymentDtoFactory;
    }

    /**
     * Process redirect from MONEI payment page
     * This controller handles GET requests from payment gateway redirects
     *
     * @return Redirect
     */
    public function execute()
    {
        try {
            $this->logInitialRequestData();

            // Extract payment and order IDs from the request
            $params = $this->request->getParams();
            $paymentId = $params['id'] ?? $params['payment_id'] ?? null;
            $orderId = $params['orderId'] ?? $params['order_id'] ?? null;

            // Try to resolve missing order ID from session
            if (!$orderId) {
                $orderId = $this->getOrderIdFromSession();
            }

            // Handle missing payment ID case
            if (!$paymentId) {
                return $this->handleMissingPaymentId($orderId);
            }

            // Try to get order ID from payment if not available
            if (!$orderId) {
                $result = $this->tryToGetOrderIdFromPayment($paymentId, $params);
                if ($result instanceof Redirect) {
                    return $result;
                }
                $orderId = $result;  // If not a redirect, it's the order ID
            }

            // Wait for any ongoing payment processing
            $this->waitForOngoingProcessing($orderId, $paymentId);

            // Main payment processing logic
            return $this->processPayment($paymentId, $orderId, $params);
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Unhandled exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->handleGenericError();
        }
    }

    /**
     * Log initial request data
     *
     * @return void
     */
    private function logInitialRequestData(): void
    {
        $params = $this->request->getParams();
        $this->logger->debug('---------------------------------------------');
        $this->logger->debug('[Complete] Payment redirect received', [
            'order_id' => $params['orderId'] ?? ($params['order_id'] ?? 'unknown'),
            'payment_id' => $params['id'] ?? ($params['payment_id'] ?? 'unknown')
        ]);
    }

    /**
     * Handle the case when payment ID is missing
     *
     * @param string|null $orderId
     * @return Redirect
     */
    private function handleMissingPaymentId(?string $orderId): Redirect
    {
        $this->logger->error('[Complete] Missing payment ID');
        $this->messageManager->addErrorMessage(
            __('There was a problem processing your payment. Please try again.')
        );

        // If we have an order ID but no payment ID, we still attempt to restore the quote
        if ($orderId) {
            $this->safelyRestoreQuote($orderId);
        }

        return $this->redirectToCart();
    }

    /**
     * Try to get order ID from payment data
     *
     * @param string $paymentId
     * @param array $params
     * @return string|Redirect Order ID or redirect to cart if handling failed payment
     */
    private function tryToGetOrderIdFromPayment(string $paymentId, array $params)
    {
        try {
            // Try to get payment from API first
            $paymentObject = $this->getPaymentService->execute($paymentId);
            $paymentData = $this->paymentDtoFactory->createFromPaymentObject($paymentObject);
            $orderId = $paymentData->getOrderId();

            if ($orderId) {
                $this->logger->info('[Complete] Retrieved order ID from payment: ' . $orderId);

                return $orderId;
            }
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Failed to get order ID from payment: ' . $e->getMessage());

            // Create a basic failed payment if we can't get details
            $orderId = $this->getOrderIdFromSession();
            if ($orderId) {
                $this->handleFailedPaymentWithSessionOrder($paymentId, $orderId, $params);

                return $this->redirectToCart();
            }
        }

        return '';
    }

    /**
     * Wait for any ongoing payment processing
     *
     * @param string|null $orderId
     * @param string $paymentId
     * @return void
     */
    private function waitForOngoingProcessing(?string $orderId, string $paymentId): void
    {
        if (!$orderId) {
            return;
        }

        if ($this->paymentProcessor->isProcessing($orderId, $paymentId)) {
            $this->logger->info('[Complete] Payment is already being processed, waiting...', [
                'order_id' => $orderId,
                'payment_id' => $paymentId
            ]);

            // Wait for processing to complete (max 5 seconds)
            $completed = $this->paymentProcessor->waitForProcessing($orderId, $paymentId, 5);

            if (!$completed) {
                $this->logger->warning('[Complete] Timeout waiting for payment processing', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]);
            } else {
                $this->logger->info('[Complete] Payment processing completed by another process', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]);
            }
        }
    }

    /**
     * Process the payment using Monei API
     *
     * @param string $paymentId
     * @param string|null $orderId
     * @param array $params
     * @return Redirect
     */
    private function processPayment(string $paymentId, ?string $orderId, array $params): Redirect
    {
        try {
            // Get payment data from the API
            $paymentObject = $this->getPaymentService->execute($paymentId);
            $paymentDTO = $this->paymentDtoFactory->createFromPaymentObject($paymentObject);

            // Update order ID if we got a valid one from the payment
            $orderId = $paymentDTO->getOrderId();

            $this->logPaymentData($paymentDTO);

            // Process the payment through the unified processor
            $result = $this->paymentProcessor->process(
                $paymentDTO->getOrderId(),
                $paymentDTO->getId(),
                $paymentDTO->getRawData()
            );

            $this->logger->debug('[Complete] Payment processing result', [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage()
            ]);

            // Special handling for MBWAY payments in PENDING status
            if ($paymentDTO->isPending() && $paymentDTO->isMbway()) {
                return $this->handlePendingMbwayPayment($paymentDTO);
            }

            // Route based on payment status
            if ($paymentDTO->isSucceeded() || $paymentDTO->isAuthorized() || $paymentDTO->isPending()) {
                return $this->handleSuccessfulPayment();
            } else {
                return $this->handleFailedPayment($paymentDTO);
            }
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Error retrieving or processing payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If we have order ID, try to process a failed payment
            if ($orderId) {
                $this->handleFailedPaymentWithSessionOrder($paymentId, $orderId, $params);
            } else {
                // No order ID available at all, just restore any possible quote
                $this->safelyRestoreQuote();
            }

            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your payment. Please try again.')
            );

            return $this->redirectToCart();
        }
    }

    /**
     * Log payment data retrieved from API
     *
     * @param PaymentDTO $paymentDTO
     * @return void
     */
    private function logPaymentData(PaymentDTO $paymentDTO): void
    {
        $this->logger->debug('[Complete] Payment data retrieved from API', [
            'payment_id' => $paymentDTO->getId(),
            'order_id' => $paymentDTO->getOrderId(),
            'status' => $paymentDTO->getStatus(),
            'payment_method' => $paymentDTO->getPaymentMethodType() ?? 'unknown'
        ]);
    }

    /**
     * Handle pending MBWAY payment
     *
     * @param PaymentDTO $paymentDTO
     * @return Redirect
     */
    private function handlePendingMbwayPayment(PaymentDTO $paymentDTO): Redirect
    {
        $this->logger->info('[Complete] MBWAY payment still in pending state, redirecting to loading page', [
            'order_id' => $paymentDTO->getOrderId(),
            'payment_id' => $paymentDTO->getId()
        ]);

        // Redirect to loading page to wait for final status
        return $this->resultRedirectFactory->create()->setPath(
            'monei/payment/loading',
            [
                'payment_id' => $paymentDTO->getId()
            ]
        );
    }

    /**
     * Handle successful payment
     *
     * @return Redirect
     */
    private function handleSuccessfulPayment(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }

    /**
     * Handle failed payment
     *
     * @param PaymentDTO $paymentDTO
     * @return Redirect
     */
    private function handleFailedPayment(PaymentDTO $paymentDTO): Redirect
    {
        $this->logger->info('[Complete] Payment status indicates failure', [
            'payment_id' => $paymentDTO->getId(),
            'status' => $paymentDTO->getStatus(),
            'error_code' => $paymentDTO->getStatusCode(),
            'error_message' => $paymentDTO->getStatusMessage()
        ]);

        // Restore the checkout session quote for failed payments
        $this->safelyRestoreQuote();

        // Get error code and error message if available
        $errorMessage = $paymentDTO->getStatusMessage();

        // Show appropriate error message based on error code or status
        if ($errorMessage) {
            // Use the specific error message from MONEI if available
            $this->messageManager->addErrorMessage(
                __('Payment error: %1', $errorMessage)
            );
        } elseif ($paymentDTO->isCanceled()) {
            $this->messageManager->addErrorMessage(
                __('Your payment was canceled. Your cart has been restored so you can try again.')
            );
        } elseif ($paymentDTO->isExpired()) {
            $this->messageManager->addErrorMessage(
                __('Your payment expired. Your cart has been restored so you can try again.')
            );
        } else {
            $this->messageManager->addErrorMessage(
                __('There was a problem processing your payment. Your cart has been restored so you can try again.')
            );
        }

        return $this->redirectToCart();
    }

    /**
     * Handle generic error case
     *
     * @return Redirect
     */
    private function handleGenericError(): Redirect
    {
        $this->messageManager->addErrorMessage(
            __('An unexpected error occurred. Please try again or contact customer support.')
        );

        $this->safelyRestoreQuote();

        return $this->redirectToCart();
    }

    /**
     * Get order ID from the last order in the checkout session
     *
     * @return string|null
     */
    private function getOrderIdFromSession(): ?string
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if ($order && $order->getIncrementId()) {
                $this->logger->info('[Complete] Found order ID in session: ' . $order->getIncrementId());

                return $order->getIncrementId();
            }

            $this->logger->error('[Complete] No valid order found in session');
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Error getting order from session: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Handle a failed payment with order ID from session
     *
     * @param string $paymentId
     * @param string $orderId
     * @param array $params
     * @return void
     */
    private function handleFailedPaymentWithSessionOrder(string $paymentId, string $orderId, array $params): void
    {
        try {
            // Create basic payment data for failure processing
            $paymentData = [
                'id' => $paymentId,
                'orderId' => $orderId,
                'status' => $params['status'] ?? Status::FAILED,
                'amount' => $params['amount'] ?? 0,
                'currency' => $params['currency'] ?? 'EUR'
            ];

            // Add error code if available in params
            if (isset($params['errorCode'])) {
                $paymentData['statusCode'] = $params['errorCode'];
            } elseif (isset($params['error_code'])) {
                $paymentData['statusCode'] = $params['error_code'];
            }

            // Create PaymentDTO and process
            $paymentDTO = $this->paymentDtoFactory->createFromArray($paymentData);
            $result = $this->paymentProcessor->process(
                $paymentDTO->getOrderId(),
                $paymentDTO->getId(),
                $paymentDTO->getRawData()
            );

            $this->logger->debug('[Complete] Failed payment processing result', [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'error_code' => $paymentDTO->getStatusCode()
            ]);

            // Show error message to customer if we have error code details
            $errorMessage = $paymentDTO->getStatusMessage();
            if ($errorMessage) {
                $this->messageManager->addErrorMessage(
                    __('Payment error: %1', $errorMessage)
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('There was a problem processing your payment. Your cart has been restored so you can try again.')
                );
            }

            // Restore the quote for the customer to try again
            $this->safelyRestoreQuote();
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Error processing failed payment', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            // Still try to restore the quote
            $this->safelyRestoreQuote();
        }
    }

    /**
     * Safely restore quote (with error handling)
     *
     * @param string|null $orderId For logging purposes
     * @return void
     */
    private function safelyRestoreQuote(?string $orderId = null): void
    {
        try {
            $this->checkoutSession->restoreQuote();
            if ($orderId) {
                $this->logger->info('[Complete] Restored quote for order: ' . $orderId);
            } else {
                $this->logger->info('[Complete] Restored quote');
            }
        } catch (\Exception $e) {
            if ($orderId) {
                $this->logger->error('[Complete] Failed to restore quote for order ' . $orderId . ': ' . $e->getMessage());
            } else {
                $this->logger->error('[Complete] Failed to restore quote: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get a redirect to cart
     *
     * @return Redirect
     */
    private function redirectToCart(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }
}

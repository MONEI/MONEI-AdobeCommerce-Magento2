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
        // Prevent caching of this sensitive payment completion page
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $redirect->setHeader('Pragma', 'no-cache', true);
        $redirect->setHeader('X-Magento-Cache-Debug', 'MISS', true);

        try {
            $this->logInitialRequestData();

            // Extract payment ID from the request
            $params = $this->request->getParams();
            $paymentId = $params['id'] ?? $params['payment_id'] ?? null;

            // Handle missing payment ID case
            if (!$paymentId) {
                return $this->handleMissingPaymentId();
            }

            // Get payment data from the API as first step
            try {
                $paymentObject = $this->getPaymentService->execute($paymentId);
                $paymentDTO = $this->paymentDtoFactory->createFromPaymentObject($paymentObject);
                $this->logPaymentData($paymentDTO);
            } catch (\Exception $e) {
                $this->logger->error('[Complete] Error retrieving payment data', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage()
                ]);
                return $this->handleGenericError();
            }

            // Wait for any ongoing payment processing
            $this->waitForOngoingProcessing($paymentDTO);

            // Main payment processing logic
            return $this->processPayment($paymentDTO);
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
        $this->logger->debug('[Complete] =============================================');
        $this->logger->debug('[Complete] Payment redirect received', [
            'order_id' => $params['orderId'] ?? ($params['order_id'] ?? 'unknown'),
            'payment_id' => $params['id'] ?? ($params['payment_id'] ?? 'unknown')
        ]);
    }

    /**
     * Handle the case when payment ID is missing
     *
     * @return Redirect
     */
    private function handleMissingPaymentId(): Redirect
    {
        $this->logger->error('[Complete] Missing payment ID');
        $this->messageManager->addErrorMessage(
            __('There was a problem processing your payment. Please try again.')
        );

        // Just restore any possible quote
        $this->safelyRestoreQuote();

        return $this->redirectToCart();
    }

    /**
     * Wait for any ongoing payment processing
     *
     * @param PaymentDTO $paymentDTO
     * @return void
     */
    private function waitForOngoingProcessing(PaymentDTO $paymentDTO): void
    {
        $orderId = $paymentDTO->getOrderId();
        $paymentId = $paymentDTO->getId();

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
     * @param PaymentDTO $paymentDTO
     * @return Redirect
     */
    private function processPayment(PaymentDTO $paymentDTO): Redirect
    {
        try {
            // Special handling for MBWAY payments in PENDING status
            if ($paymentDTO->isPending() && !$paymentDTO->isMultibanco()) {
                return $this->handlePendingPayment($paymentDTO);
            }

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

            // Route based on payment status only, not the processor result
            if ($paymentDTO->isSucceeded() || $paymentDTO->isAuthorized()) {
                return $this->handleSuccessfulPayment();
            } else {
                return $this->handleFailedPayment($paymentDTO);
            }
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Error processing payment', [
                'payment_id' => $paymentDTO->getId(),
                'order_id' => $paymentDTO->getOrderId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Process the failed payment
            $this->handleFailedPayment($paymentDTO);

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
    private function handlePendingPayment(PaymentDTO $paymentDTO): Redirect
    {
        $this->logger->info('[Complete] Payment still in pending state, redirecting to loading page', [
            'order_id' => $paymentDTO->getOrderId(),
            'payment_id' => $paymentDTO->getId()
        ]);

        // Redirect to loading page to wait for final status
        return $this->resultRedirectFactory->create()->setPath(
            'monei/payment/loading',
            [
                'payment_id' => $paymentDTO->getId(),
                'order_id' => $paymentDTO->getOrderId()
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

        try {
            // Process the failed payment
            $result = $this->paymentProcessor->process(
                $paymentDTO->getOrderId(),
                $paymentDTO->getId(),
                $paymentDTO->getRawData()
            );

            $this->logger->debug('[Complete] Failed payment processing result', [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Error processing failed payment', [
                'order_id' => $paymentDTO->getOrderId(),
                'payment_id' => $paymentDTO->getId(),
                'error' => $e->getMessage()
            ]);
        }

        // Restore the checkout session quote for failed payments
        $this->safelyRestoreQuote($paymentDTO->getOrderId());

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

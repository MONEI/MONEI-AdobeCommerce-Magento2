<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Service\Logger;

/**
 * Controller for handling payment completion redirects from MONEI
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
     * @param OrderRepositoryInterface $orderRepository
     * @param RedirectFactory $resultRedirectFactory
     * @param Logger $logger
     * @param PaymentProcessorInterface $paymentProcessor
     * @param MoneiApiClient $apiClient
     * @param GetPaymentInterface $getPaymentService
     * @param HttpRequest $request
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        RedirectFactory $resultRedirectFactory,
        Logger $logger,
        PaymentProcessorInterface $paymentProcessor,
        MoneiApiClient $apiClient,
        GetPaymentInterface $getPaymentService,
        HttpRequest $request
    ) {
        $this->orderRepository = $orderRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->logger = $logger;
        $this->paymentProcessor = $paymentProcessor;
        $this->apiClient = $apiClient;
        $this->getPaymentService = $getPaymentService;
        $this->request = $request;
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
            $params = $this->request->getParams();
            $this->logger->debug('---------------------------------------------');
            $this->logger->debug('[Complete] Payment redirect received', [
                'order_id' => $params['orderId'] ?? 'unknown',
                'payment_id' => $params['id'] ?? 'unknown',
                'status' => $params['status'] ?? 'unknown'
            ]);

            // Check if we have the required parameters
            if (!isset($params['orderId']) || !isset($params['id']) || !isset($params['status'])) {
                $this->logger->error('[Complete] Missing required parameters');

                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }

            $orderId = $params['orderId'];
            $paymentId = $params['id'];

            // Check if payment is already being processed
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
            } else {
                // If the payment is not being processed, fetch fresh data from the API
                $this->logger->debug('[Complete] Fetching payment data from API', [
                    'payment_id' => $paymentId
                ]);

                try {
                    // Get the payment data from the API to ensure it's up-to-date
                    $paymentObject = $this->getPaymentService->execute($paymentId);

                    // Convert the payment object to array
                    $paymentData = (array)$paymentObject;

                    // Create a PaymentDTO instance with the API data
                    $paymentDTO = PaymentDTO::fromArray($paymentData);

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
                } catch (\Exception $e) {
                    $this->logger->error('[Complete] Error processing payment', [
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'error' => $e->getMessage()
                    ]);

                    // If API fetch fails, fallback to using redirect data
                    try {
                        // Create PaymentDTO from redirect parameters
                        $paymentDTO = PaymentDTO::fromArray($params);
                        $this->paymentProcessor->process(
                            $paymentDTO->getOrderId(),
                            $paymentDTO->getId(),
                            $paymentDTO->getRawData()
                        );
                    } catch (\Exception $fallbackEx) {
                        $this->logger->error('[Complete] Fallback processing failed: ' . $fallbackEx->getMessage());
                    }
                }
            }

            // Determine redirect based on payment status
            if (isset($params['status']) &&
                ($params['status'] === PaymentStatus::SUCCEEDED || $params['status'] === PaymentStatus::AUTHORIZED)) {
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            }

            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->logger->error('[Complete] Unhandled exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
    }
}

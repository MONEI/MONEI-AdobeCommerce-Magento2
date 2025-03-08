<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\Service\ValidateCallbackSignatureInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentDataProvider\CallbackPaymentDataProvider;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Controller for managing callbacks from Monei system
 */
class Callback extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @var string
     */
    private string $errorMessage = '';

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var CallbackPaymentDataProvider
     */
    private CallbackPaymentDataProvider $callbackPaymentDataProvider;

    /**
     * @var GenerateInvoiceInterface
     */
    private GenerateInvoiceInterface $generateInvoiceService;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var ValidateCallbackSignatureInterface
     */
    private ValidateCallbackSignatureInterface $validateCallbackSignatureService;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param \Monei\MoneiPayment\Model\PaymentDataProvider\CallbackPaymentDataProvider $callbackPaymentDataProvider
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param JsonFactory $resultJsonFactory
     * @param PaymentProcessor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param ValidateCallbackSignatureInterface $validateCallbackSignatureService
     * @param MoneiApiClient $apiClient
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        Context $context,
        Logger $logger,
        \Monei\MoneiPayment\Model\PaymentDataProvider\CallbackPaymentDataProvider $callbackPaymentDataProvider,
        GenerateInvoiceInterface $generateInvoiceService,
        JsonFactory $resultJsonFactory,
        PaymentProcessor $paymentProcessor,
        OrderRepositoryInterface $orderRepository,
        ValidateCallbackSignatureInterface $validateCallbackSignatureService,
        MoneiApiClient $apiClient,
        TransactionFactory $transactionFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->callbackPaymentDataProvider = $callbackPaymentDataProvider;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->orderRepository = $orderRepository;
        $this->validateCallbackSignatureService = $validateCallbackSignatureService;
        $this->apiClient = $apiClient;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * Execute action based on request
     *
     * This endpoint handles asynchronous payment callback notifications from MONEI.
     * It returns an immediate 200 OK to acknowledge receipt, then processes the payment data.
     *
     * @return Json
     */
    public function execute()
    {
        try {
            $this->logger->debug('---------------------------------------------');
            $this->logger->debug('[Callback] Payment callback received');

            // Return 200 OK immediately to acknowledge receipt
            http_response_code(200);

            $content = (string) $this->getRequest()->getContent();
            $signatureHeader = $this->getRequest()->getHeaders()->get('MONEI-Signature');

            // Validate signature if present
            if ($signatureHeader && !$this->validateSignature($content, $signatureHeader)) {
                $this->logger->warning('[Callback] Invalid signature');

                return $this->resultJsonFactory->create()->setData(['error' => 'Invalid signature']);
            }

            // Extract payment data from callback
            try {
                $paymentData = $this->callbackPaymentDataProvider->extractFromCallback($content, $signatureHeader ?? '');
            } catch (LocalizedException $e) {
                $this->logger->error('[Callback] Failed to extract payment data: ' . $e->getMessage(), [
                    'exception' => $e
                ]);

                return $this->resultJsonFactory->create()->setData(['error' => 'Invalid payment data: ' . $e->getMessage()]);
            }

            // Process the payment
            $this->processPayment($paymentData);

            $this->logger->debug('[Callback] Payment callback processed successfully');

            return $this->resultJsonFactory->create()->setData(['received' => true]);
        } catch (NoSuchEntityException $e) {
            $this->logger->error('[Callback] Order not found: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return $this->resultJsonFactory->create()->setData(['error' => 'Order not found']);
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error processing callback: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return $this->resultJsonFactory->create()->setData(['error' => 'Internal error processing callback']);
        }
    }

    /**
     * Process payment from callback data
     *
     * This method handles the business logic of updating order status based on payment status.
     * It uses a database transaction to ensure data integrity during updates.
     *
     * @param PaymentDTO $paymentData Payment data received from MONEI
     * @return void
     */
    private function processPayment(PaymentDTO $paymentData): void
    {
        try {
            $orderId = $paymentData->getOrderId();
            if (!$orderId) {
                $this->logger->warning('[Callback] Missing order ID in payment data');

                return;
            }

            // Load the order
            $order = $this->orderRepository->get($orderId);
            if (!$order || !$order->getEntityId()) {
                $this->logger->warning('[Callback] Order not found: ' . $orderId);

                return;
            }

            $status = $paymentData->getStatus();
            $this->logger->debug('[Callback] Processing payment with status: ' . $status, [
                'order_id' => $order->getIncrementId(),
                'payment_id' => $paymentData->getId()
            ]);

            // Validate that the status is a known MONEI status
            if (!in_array($status, PaymentStatus::getAllowableEnumValues(), true)) {
                $this->logger->warning('[Callback] Unknown payment status: ' . $status);

                return;
            }

            // Start a database transaction for atomic operations
            /** @var Transaction $transaction */
            $transaction = $this->transactionFactory->create();

            try {
                // Process payment based on status
                $this->paymentProcessor->processPayment($order, $paymentData);

                // Generate invoice only for successful payments
                if ($status === PaymentStatus::SUCCEEDED) {
                    $this->generateInvoiceService->execute($order, $paymentData->getRawData());
                }

                // Commit the transaction
                $transaction->save();
            } catch (\Exception $e) {
                // Roll back any changes if an error occurred
                $transaction->rollback();

                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error processing payment: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    /**
     * Validate callback signature
     *
     * Verifies that the callback is authentic by checking the signature provided by MONEI.
     *
     * @param string $payload The request body content
     * @param string $signature The MONEI-Signature header value
     * @return bool True if signature is valid, false otherwise
     */
    private function validateSignature(string $payload, string $signature): bool
    {
        try {
            return $this->validateCallbackSignatureService->validate($payload, $signature);
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error validating signature: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return false;
        }
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null to skip CSRF check for this action.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;  // Skip CSRF validation for callback requests
    }

    /**
     * Perform custom CSRF validation for callbacks.
     * Return null to skip CSRF check for this action.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $content = (string) $request->getContent();
        $signatureHeader = $request->getHeaders()->get('MONEI-Signature');

        $this->logger->debug('---------------------------------------------');
        $this->logger->debug('[Callback validation] Validating request');

        // If there's a signature, validate it
        if ($signatureHeader) {
            return $this->validateSignature($content, $signatureHeader);
        }

        // If no signature, allow the request (will be validated in execute)
        return true;
    }
}

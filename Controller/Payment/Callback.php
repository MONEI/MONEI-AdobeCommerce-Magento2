<?php

/**
 * @author Monei Team
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
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\Service\ValidateCallbackSignatureInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\PaymentDataProvider\CallbackPaymentDataProvider;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;

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
     * @param Context $context
     * @param Logger $logger
     * @param \Monei\MoneiPayment\Model\PaymentDataProvider\CallbackPaymentDataProvider $callbackPaymentDataProvider
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param JsonFactory $resultJsonFactory
     * @param PaymentProcessor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param ValidateCallbackSignatureInterface $validateCallbackSignatureService
     * @param MoneiApiClient $apiClient
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
        MoneiApiClient $apiClient
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
    }

    /**
     * Execute action based on request
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

            $content = $this->getRequest()->getContent();
            $signatureHeader = $this->getRequest()->getHeader('MONEI-Signature');

            // Validate signature if present
            if ($signatureHeader && !$this->validateSignature($content, $signatureHeader)) {
                $this->logger->warning('[Callback] Invalid signature');
                return $this->resultJsonFactory->create()->setData(['error' => 'Invalid signature']);
            }

            // Extract payment data from callback
            $paymentData = $this->callbackPaymentDataProvider->extractFromCallback($content, $signatureHeader ?? '');

            // Process the payment
            $this->processPayment($paymentData);

            $this->logger->debug('[Callback] Payment callback processed successfully');
            return $this->resultJsonFactory->create()->setData(['received' => true]);
        } catch (\Exception $e) {
            $this->logger->error('[Callback] Error processing callback: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return $this->resultJsonFactory->create()->setData(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process payment from callback data
     *
     * @param PaymentDTO $paymentData
     * @throws LocalizedException
     * @throws NoSuchEntityException
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

            // Process payment based on status
            switch ($status) {
                case 'SUCCEEDED':
                    $this->paymentProcessor->processPayment($order, $paymentData);
                    // Generate invoice if needed
                    $this->generateInvoiceService->execute($order, $paymentData);
                    break;

                case 'FAILED':
                    $this->paymentProcessor->processPayment($order, $paymentData);
                    break;

                case 'CANCELED':
                    $this->paymentProcessor->processPayment($order, $paymentData);
                    break;

                case 'AUTHORIZED':
                    $this->paymentProcessor->processPayment($order, $paymentData);
                    break;

                default:
                    $this->logger->warning('[Callback] Unhandled payment status: ' . $status);
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
     * @param string $payload
     * @param string $signature
     * @return bool
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
        return null; // Skip CSRF validation for callback requests
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
        $content = $request->getContent();
        $signatureHeader = $request->getHeader('MONEI-Signature');

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

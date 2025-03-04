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
use Monei\MoneiPayment\Api\Service\ValidateWebhookSignatureInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\PaymentDataProvider\WebhookPaymentDataProvider;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;

/**
 * Controller for managing callback from Monei system
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
     * @var WebhookPaymentDataProvider
     */
    private WebhookPaymentDataProvider $webhookPaymentDataProvider;

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
     * @var ValidateWebhookSignatureInterface
     */
    private ValidateWebhookSignatureInterface $validateWebhookSignatureService;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param WebhookPaymentDataProvider $webhookPaymentDataProvider
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param JsonFactory $resultJsonFactory
     * @param PaymentProcessor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param ValidateWebhookSignatureInterface $validateWebhookSignatureService
     * @param MoneiApiClient $apiClient
     */
    public function __construct(
        Context $context,
        Logger $logger,
        WebhookPaymentDataProvider $webhookPaymentDataProvider,
        GenerateInvoiceInterface $generateInvoiceService,
        JsonFactory $resultJsonFactory,
        PaymentProcessor $paymentProcessor,
        OrderRepositoryInterface $orderRepository,
        ValidateWebhookSignatureInterface $validateWebhookSignatureService,
        MoneiApiClient $apiClient
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->webhookPaymentDataProvider = $webhookPaymentDataProvider;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->orderRepository = $orderRepository;
        $this->validateWebhookSignatureService = $validateWebhookSignatureService;
        $this->apiClient = $apiClient;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        // Force HTTP 200 response immediately
        http_response_code(200);

        $content = $this->getRequest()->getContent();
        $signatureHeader = $this->getRequest()->getHeader('MONEI-Signature');

        $this->logger->debug('---------------------------------------------');
        $this->logger->debug('[Callback controller] Webhook received - Forced 200 response');
        $this->logger->debug('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));
        $this->logger->debug('[Signature header] ' . $signatureHeader);

        try {
            // Extract payment data from webhook
            $this->logger->debug('[Callback controller] Extracting payment data from webhook');
            $paymentData = $this->webhookPaymentDataProvider->extractFromWebhook($content, $signatureHeader);
            $this->logger->debug('[Callback controller] Payment data extracted successfully', [
                'payment_id' => $paymentData->getId(),
                'order_id' => $paymentData->getOrderId(),
                'status' => $paymentData->getStatus()
            ]);

            // Process the payment
            $this->logger->debug('[Callback controller] Processing payment');
            $this->processPayment($paymentData);
            $this->logger->debug('[Callback controller] Payment processed successfully');

            // Output JSON response directly
            $response = ['success' => true];
            $this->logger->debug('[Callback controller] Returning success response');
        } catch (Exception $e) {
            // Log the error but still return 200 OK to acknowledge receipt
            $this->logger->critical('[Callback error] ' . $e->getMessage());
            $this->logger->critical('[Callback error] Stack trace: ' . $e->getTraceAsString());
            $this->logger->critical('[Request body] ' . ($content ?? 'No content'));

            // Output JSON error response directly
            $response = [
                'success' => false,
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }

        // Output the JSON response directly
        header('Content-Type: application/json');
        echo json_encode($response);

        // Terminate execution to prevent Magento from modifying the response
        exit;
    }

    /**
     * Process the payment data
     *
     * @param PaymentDTO $paymentData
     * @return void
     */
    private function processPayment(PaymentDTO $paymentData): void
    {
        try {
            // Get the order from the repository
            $orderId = $paymentData->getOrderId();
            if (!$orderId) {
                $this->logger->critical('[Payment processing error] No order ID in payment data', [
                    'payment_id' => $paymentData->getId()
                ]);
                return;
            }

            $this->logger->debug('[Callback controller] Processing payment for order #' . $orderId);

            try {
                $order = $this->orderRepository->get($orderId);
                $this->logger->debug('[Callback controller] Order found', [
                    'order_id' => $orderId,
                    'order_status' => $order->getStatus()
                ]);

                // Use the payment processor to handle the payment
                $this->logger->debug('[Callback controller] Calling payment processor');
                $this->paymentProcessor->processPayment($order, $paymentData);
                $this->logger->debug('[Callback controller] Payment processor completed successfully');
            } catch (NoSuchEntityException $e) {
                $this->logger->critical('[Payment processing error] Order not found', [
                    'payment_id' => $paymentData->getId(),
                    'order_id' => $orderId
                ]);
                throw $e;
            }
        } catch (Exception $e) {
            $this->logger->critical('[Payment processing error] ' . $e->getMessage(), [
                'payment_id' => $paymentData->getId(),
                'order_id' => $paymentData->getOrderId()
            ]);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        /** @var ResponseHttp $response */
        $response = $this->getResponse();
        // For CSRF validation, we should still return 403 as this is a security check
        $response->setHttpResponseCode(403);
        $response->setReasonPhrase($this->errorMessage);

        return new InvalidRequestException($response);
    }

    /**
     * @inheritdoc
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $content = $request->getContent();
        $signatureHeader = $request->getHeader('MONEI-Signature');

        $this->logger->debug('---------------------------------------------');
        $this->logger->debug('[Callback validation] Validating CSRF');
        $this->logger->debug('[Signature header] ' . $signatureHeader);
        $this->logger->debug('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));

        try {
            // Use direct MoneiClient approach for signature verification
            $this->logger->debug('[Callback validation] Verifying webhook signature using direct approach');

            // Get the Monei SDK instance
            $moneiSdk = $this->apiClient->getMoneiSdk();

            // Directly verify the signature
            $payload = $moneiSdk->verifySignature($content, $signatureHeader);

            if ($payload) {
                $this->logger->debug('[Callback validation] Signature verified successfully');
                return true;
            } else {
                $this->logger->critical('[Signature verification error] Verification returned false');
                return false;
            }
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical('[Signature verification error] ' . $e->getMessage());
            $this->logger->critical('[Signature verification error] Stack trace: ' . $e->getTraceAsString());
            $this->logger->critical('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));
            return false;
        }
    }
}

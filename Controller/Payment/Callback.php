<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\PaymentDataProvider\WebhookPaymentDataProvider;
use Monei\MoneiPayment\Service\Logger;

/**
 * Controller for managing callbacks from Monei system
 */
class Callback implements CsrfAwareActionInterface, HttpPostActionInterface
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
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var WebhookPaymentDataProvider
     */
    private WebhookPaymentDataProvider $webhookPaymentDataProvider;

    /**
     * @var PaymentProcessorInterface
     */
    private PaymentProcessorInterface $paymentProcessor;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $apiClient;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var HttpResponse
     */
    private HttpResponse $response;

    /**
     * @var object|null
     */
    private $verifiedPayment = null;

    /**
     * @param Logger $logger
     * @param JsonFactory $resultJsonFactory
     * @param WebhookPaymentDataProvider $webhookPaymentDataProvider
     * @param PaymentProcessorInterface $paymentProcessor
     * @param MoneiApiClient $apiClient
     * @param OrderRepositoryInterface $orderRepository
     * @param HttpRequest $request
     * @param HttpResponse $response
     */
    public function __construct(
        Logger $logger,
        JsonFactory $resultJsonFactory,
        WebhookPaymentDataProvider $webhookPaymentDataProvider,
        PaymentProcessorInterface $paymentProcessor,
        MoneiApiClient $apiClient,
        OrderRepositoryInterface $orderRepository,
        HttpRequest $request,
        HttpResponse $response
    ) {
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->webhookPaymentDataProvider = $webhookPaymentDataProvider;
        $this->paymentProcessor = $paymentProcessor;
        $this->apiClient = $apiClient;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Execute action based on request
     *
     * This endpoint handles asynchronous payment callback notifications from MONEI.
     * It processes payment data and returns appropriate HTTP response codes to allow for retries on failure.
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

            // Process the verified payment from validateForCsrf
            if ($this->verifiedPayment) {
                // Log the payment object type for debugging
                $this->logger->debug('[Callback] Payment object type', [
                    'object_type' => gettype($this->verifiedPayment),
                    'is_object' => is_object($this->verifiedPayment),
                    'class' => is_object($this->verifiedPayment) ? get_class($this->verifiedPayment) : 'not_an_object'
                ]);

                // Convert Payment object to array
                $paymentData = (array)$this->verifiedPayment;

                $this->logger->debug('[Callback] Signature verified, processing payment', [
                    'payment_id' => $paymentData['id'] ?? 'unknown',
                    'order_id' => $paymentData['orderId'] ?? 'unknown',
                    'status' => $paymentData['status'] ?? 'unknown',
                    'available_keys' => array_keys($paymentData)
                ]);

                // Validate the payment data has required fields
                if (empty($paymentData['id']) || empty($paymentData['orderId'])) {
                    $this->logger->error('[Callback] Payment object missing required fields', [
                        'received_fields' => array_keys($paymentData)
                    ]);

                    $response = $this->resultJsonFactory->create();
                    $response->setHttpResponseCode(400);
                    return $response->setData(['error' => 'Missing required payment data fields']);
                }

                try {
                    // Create a PaymentDTO instance from the array data
                    $paymentDTO = PaymentDTO::fromArray($paymentData);

                    // Process the payment with the payment processor
                    $result = $this->paymentProcessor->process(
                        $paymentDTO->getOrderId(),
                        $paymentDTO->getId(),
                        $paymentDTO->getRawData()
                    );

                    $this->logger->debug('[Callback] Payment processing result', [
                        'success' => $result->isSuccess(),
                        'message' => $result->getMessage()
                    ]);

                    if (!$result->isSuccess()) {
                        $this->logger->error('[Callback] Payment processing failed: ' . $result->getMessage());
                        $response = $this->resultJsonFactory->create();
                        $response->setHttpResponseCode(422);

                        return $response->setData(['error' => $result->getMessage()]);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('[Callback] Error creating PaymentDTO: ' . $e->getMessage(), [
                        'payment_id' => $paymentData['id'] ?? 'unknown'
                    ]);
                    $response = $this->resultJsonFactory->create();
                    $response->setHttpResponseCode(500);

                    return $response->setData(['error' => 'Error processing payment data: ' . $e->getMessage()]);
                }
            } else {
                $this->logger->error('[Callback] Invalid signature or payment data');
                $response = $this->resultJsonFactory->create();
                $response->setHttpResponseCode(401);

                return $response->setData(['error' => 'Invalid signature']);
            }

            return $this->resultJsonFactory->create()->setData(['received' => true]);
        } catch (Exception $e) {
            $this->logger->error('[Callback] Error processing callback: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            $response = $this->resultJsonFactory->create();
            $response->setHttpResponseCode(500);

            return $response->setData(['error' => 'Internal error processing callback: ' . $e->getMessage()]);
        }
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $this->response->setHttpResponseCode(403);
        $this->response->setReasonPhrase($this->errorMessage);

        return new InvalidRequestException($this->response);
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        try {
            $body = file_get_contents('php://input');
            $header = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? '';

            if (empty($header)) {
                $this->errorMessage = 'Missing signature header';
                $this->logger->critical('[Callback CSRF] Missing signature header');

                return false;
            }

            $signature = $header;

            // Verify signature and store the result for later use in execute()
            $this->verifiedPayment = $this->apiClient->getMoneiSdk()->verifySignature($body, $signature);
            $isValid = !empty($this->verifiedPayment);

            if (!$isValid) {
                $this->errorMessage = 'Invalid signature';
            }

            return $isValid;
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical('[Callback CSRF] ' . $e->getMessage());

            return false;
        }
    }
}

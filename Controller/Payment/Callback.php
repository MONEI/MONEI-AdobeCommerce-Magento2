<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as Config;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Controller\Payment\InvalidPaymentDataException;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Logger;
use Exception;

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
     * @var PaymentDTOFactory
     */
    private PaymentDTOFactory $paymentDtoFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param Logger $logger
     * @param JsonFactory $resultJsonFactory
     * @param PaymentProcessorInterface $paymentProcessor
     * @param MoneiApiClient $apiClient
     * @param OrderRepositoryInterface $orderRepository
     * @param HttpRequest $request
     * @param HttpResponse $response
     * @param PaymentDTOFactory $paymentDtoFactory
     * @param Config $config
     */
    public function __construct(
        Logger $logger,
        JsonFactory $resultJsonFactory,
        PaymentProcessorInterface $paymentProcessor,
        MoneiApiClient $apiClient,
        OrderRepositoryInterface $orderRepository,
        HttpRequest $request,
        HttpResponse $response,
        PaymentDTOFactory $paymentDtoFactory,
        Config $config
    ) {
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->apiClient = $apiClient;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->response = $response;
        $this->paymentDtoFactory = $paymentDtoFactory;
        $this->config = $config;
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
        $this->logger->info('Payment callback received');

        try {
            // Verify CSRF protection signature
            if (!$this->verifySignature()) {
                return $this->sendErrorResponse();
            }

            // Get payment data from request
            $payment = $this->getPaymentFromRequest();
            if (!$payment) {
                return $this->sendErrorResponse();
            }

            $this->logger->logApiRequest('callback_process', [
                'payment_id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'order_id' => $payment->getOrderId() ?? null
            ]);

            // Verify payment has required fields
            if (empty($payment->getId()) || empty($payment->getStatus())) {
                $this->logger->logApiError('callback_process', 'Payment object missing required fields', [
                    'payment_id' => $payment->getId() ?? 'null',
                    'status' => $payment->getStatus() ?? 'null',
                    'order_id' => $payment->getOrderId() ?? 'null'
                ]);

                return $this->sendErrorResponse();
            }

            // Process payment
            $result = $this->paymentProcessor->process(
                $payment->getOrderId(),
                $payment->getId(),
                $payment->getRawData()
            );

            $this->logger->logApiResponse('callback_process', [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'payment_id' => $payment->getId(),
                'order_id' => $payment->getOrderId() ?? null
            ]);

            if (!$result->isSuccess()) {
                $this->logger->logApiError('callback_process', 'Payment processing failed: ' . $result->getMessage(), [
                    'payment_id' => $payment->getId(),
                    'order_id' => $payment->getOrderId() ?? null
                ]);
                $this->errorMessage = $result->getMessage();

                return $this->sendErrorResponse();
            }
        } catch (InvalidPaymentDataException $e) {
            $this->logger->logApiError('callback_process', 'Error creating PaymentDTO: ' . $e->getMessage(), [
                'received_data' => $this->getRequest()->getContent()
            ]);
            $this->errorMessage = $e->getMessage();

            return $this->sendErrorResponse();
        } catch (InvalidSignatureException $e) {
            $this->logger->logApiError('callback_process', 'Invalid signature or payment data', [
                'received_data' => $this->getRequest()->getContent()
            ]);
            $this->errorMessage = $e->getMessage();

            return $this->sendErrorResponse();
        } catch (\Exception $e) {
            $this->logger->logApiError('callback_process', 'Error processing callback: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->errorMessage = $e->getMessage();

            return $this->sendErrorResponse();
        }

        return $this->sendSuccessResponse();
    }

    /**
     * Verify that the callback has a valid CSRF protection signature
     */
    private function verifySignature(): bool
    {
        if (!$this->getRequest()->getHeader('monei-signature')) {
            $this->errorMessage = 'Missing signature header';
            $this->logger->logApiError('callback_signature', 'Missing signature header', [
                'headers' => $this->getRequest()->getHeaders()->toArray()
            ]);

            return false;
        }

        try {
            return $this->apiClient->getMoneiSdk()->verifySignature(
                $this->getRequest()->getContent(),
                $this->getRequest()->getHeader('monei-signature')
            );
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->logApiError('callback_signature', $e->getMessage(), [
                'headers' => $this->getRequest()->getHeaders()->toArray(),
                'content_length' => strlen($this->getRequest()->getContent())
            ]);

            return false;
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

    private function getPaymentFromRequest()
    {
        // Implementation of getPaymentFromRequest method
    }

    private function sendErrorResponse()
    {
        // Implementation of sendErrorResponse method
    }

    private function sendSuccessResponse()
    {
        // Implementation of sendSuccessResponse method
    }
}

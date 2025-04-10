<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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
        // Set no-cache headers to prevent Varnish caching
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $this->response->setHeader('Pragma', 'no-cache', true);
        $this->response->setHeader('X-Magento-Cache-Debug', 'MISS', true);

        try {
            $this->logger->debug('[Callback] =============================================');
            $this->logger->debug('[Callback] Payment callback received');

            // Process the verified payment from validateForCsrf
            if ($this->verifiedPayment) {
                // Convert Payment object to array
                $paymentData = (array) $this->verifiedPayment;

                $this->logger->debug('[Callback] Processing payment', [
                    'payment_id' => $paymentData['id'] ?? 'unknown',
                    'order_id' => $paymentData['orderId'] ?? 'unknown',
                    'status' => $paymentData['status'] ?? 'unknown',
                    'signature_verified' => true
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
                    // Create a PaymentDTO object
                    $paymentDTO = $this->paymentDtoFactory->createFromArray($paymentData);

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
                        $this->logger->error('[Callback] Processing failed: ' . $result->getMessage());
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
            $body = $request->getContent();
            $signatureHeader = $request->getHeader('MONEI-Signature');

            if (empty($signatureHeader)) {
                $this->errorMessage = 'Missing signature header';
                $this->logger->critical('[Callback CSRF] Missing signature header');

                return false;
            }

            $signature = $signatureHeader;

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

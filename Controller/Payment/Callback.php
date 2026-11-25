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
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Connect\CallbackVerifier;
use Monei\MoneiPayment\Service\Logger;
use Exception;

/**
 * Controller for managing callbacks from Monei system
 */
class Callback implements CsrfAwareActionInterface, HttpPostActionInterface
{
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
     * @var CallbackVerifier
     */
    private CallbackVerifier $callbackVerifier;

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
     * @param CallbackVerifier $callbackVerifier
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
        CallbackVerifier $callbackVerifier,
        OrderRepositoryInterface $orderRepository,
        HttpRequest $request,
        HttpResponse $response,
        PaymentDTOFactory $paymentDtoFactory,
        Config $config
    ) {
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->callbackVerifier = $callbackVerifier;
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

            $signature = $this->request->getHeader('MONEI-Signature');
            $decision = $this->callbackVerifier->verify(
                (string) $this->request->getContent(),
                is_string($signature) && $signature !== '' ? $signature : null
            );
            $paymentData = $decision['payment'];

            if ($paymentData !== null) {
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
                        $paymentDTO->getRawData(),
                        $decision['order']
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
            } elseif ($decision['status'] === 200) {
                // A payment that must not change the order: 200, so MONEI does not retry.
                $this->logger->warning('[Callback] Payment not processed: ' . $decision['reason']);
            } else {
                $this->logger->error('[Callback] Callback refused: ' . $decision['reason']);
                $response = $this->resultJsonFactory->create();
                $response->setHttpResponseCode($decision['status']);

                return $response->setData(['error' => 'Callback refused']);
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
        return null;
    }

    /**
     * The callback has no form key. execute() checks it with CallbackVerifier, which can answer 400, 401 or 503;
     * a refusal here could only answer 403.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

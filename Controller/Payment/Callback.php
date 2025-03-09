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
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\PaymentDataProvider\WebhookPaymentDataProvider;
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
     * @param Context $context
     * @param Logger $logger
     * @param JsonFactory $resultJsonFactory
     * @param WebhookPaymentDataProvider $webhookPaymentDataProvider
     * @param PaymentProcessorInterface $paymentProcessor
     * @param MoneiApiClient $apiClient
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        Logger $logger,
        JsonFactory $resultJsonFactory,
        WebhookPaymentDataProvider $webhookPaymentDataProvider,
        PaymentProcessorInterface $paymentProcessor,
        MoneiApiClient $apiClient,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->webhookPaymentDataProvider = $webhookPaymentDataProvider;
        $this->paymentProcessor = $paymentProcessor;
        $this->apiClient = $apiClient;
        $this->orderRepository = $orderRepository;
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

            // Get request content and signature
            $rawBody = $this->getRequest()->getContent();
            $signatureHeader = $this->getRequest()->getHeader('MONEI-Signature');
            $signature = is_array($signatureHeader) ? implode(',', $signatureHeader) : (string)$signatureHeader;
            
            // Verify signature and get payment data in one step
            $paymentObj = $this->apiClient->getMoneiSdk()->verifySignature($rawBody, $signature);
            
            if ($paymentObj) {
                $this->logger->debug('[Callback] Signature verified, processing payment', [
                    'payment_id' => $paymentObj->id ?? 'unknown',
                    'order_id' => $paymentObj->orderId ?? 'unknown',
                    'status' => $paymentObj->status ?? 'unknown'
                ]);
                
                // Convert payment object to array
                $paymentData = (array)$paymentObj;
                
                try {
                    // Create a PaymentDTO instance
                    $paymentDTO = PaymentDTO::fromArray($paymentData);
                    
                    // Process the payment with the payment processor
                    if ($paymentDTO->getOrderId()) {
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
                    } else {
                        $this->logger->error('[Callback] Missing order ID in payment data');
                        $response = $this->resultJsonFactory->create();
                        $response->setHttpResponseCode(400);
                        return $response->setData(['error' => 'Missing order ID in payment data']);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('[Callback] Error creating PaymentDTO: ' . $e->getMessage(), [
                        'payment_id' => $paymentObj->id ?? 'unknown'
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
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();
        $response->setHttpResponseCode(403);
        $response->setReasonPhrase($this->errorMessage);

        return new InvalidRequestException($response);
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        try {
            $body = $request->getContent();
            $header = $request->getHeader('MONEI-Signature');
            
            if (empty($header)) {
                $this->errorMessage = 'Missing signature header';
                $this->logger->critical('[Callback CSRF] Missing signature header');
                return false;
            }
            
            $signature = is_array($header) ? implode(',', $header) : (string)$header;
            
            // Verify signature - we don't need to store the payment object here
            $isValid = !empty($this->apiClient->getMoneiSdk()->verifySignature($body, $signature));
            
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

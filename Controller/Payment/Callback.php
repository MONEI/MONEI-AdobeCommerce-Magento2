<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Order\PaymentProcessor;

/**
 * Controller for managing callback from Monei system.
 */
class Callback implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /** Controller source identifier. */
    private const SOURCE = 'callback';

    /**
     * Stores error messages that occur during payment processing.
     *
     * @var string
     */
    private $errorMessage = '';

    /** @var Context */
    private $context;

    /** @var SerializerInterface */
    private $serializer;

    /** @var MoneiPaymentModuleConfigInterface */
    private $moduleConfig;

    /** @var Logger */
    private $logger;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var GenerateInvoiceInterface */
    private $generateInvoiceService;

    /** @var SetOrderStatusAndStateInterface */
    private $setOrderStatusAndStateService;

    /** @var MagentoRedirect */
    private $resultRedirectFactory;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var PaymentProcessor */
    private $paymentProcessor;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param SerializerInterface $serializer
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService
     * @param MagentoRedirect $resultRedirectFactory
     * @param JsonFactory $resultJsonFactory
     * @param PaymentProcessor $paymentProcessor
     */
    public function __construct(
        Context $context,
        SerializerInterface $serializer,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger,
        StoreManagerInterface $storeManager,
        GenerateInvoiceInterface $generateInvoiceService,
        SetOrderStatusAndStateInterface $setOrderStatusAndStateService,
        MagentoRedirect $resultRedirectFactory,
        JsonFactory $resultJsonFactory,
        PaymentProcessor $paymentProcessor
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->setOrderStatusAndStateService = $setOrderStatusAndStateService;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->serializer = $serializer;
        $this->context = $context;
        $this->paymentProcessor = $paymentProcessor;
    }

    /**
     * Execute action based on request and return result.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $responseData = ['success' => true];
        $responseCode = 200;

        try {
            $content = $this->context->getRequest()->getContent();
            $body = $this->serializer->unserialize($content);

            if (!isset($body['orderId'], $body['status'], $body['id'])) {
                $this->logger->error('[Callback request failed] Missing required parameters');
                $this->logger->error('[Request body] ' . $content);
                $responseData = ['success' => false, 'message' => 'Missing required parameters'];
                $responseCode = 400;

                return $result->setHttpResponseCode($responseCode)->setData($responseData);
            }

            // Process the payment through the centralized service
            $processed = $this->paymentProcessor->processPayment($body, self::SOURCE);

            if (!$processed) {
                $this->logger->info(\sprintf(
                    '[Payment processing not completed] Order %s, status %s',
                    $body['orderId'],
                    $body['status']
                ));
                $responseData['info'] = 'Payment processing was not completed';
            }
        } catch (\Exception $e) {
            $this->logger->critical('[Error in Callback controller] ' . $e->getMessage());
            $this->logger->critical('[Request body] ' . ($content ?? 'not available'));
            $responseData = ['success' => false, 'message' => $e->getMessage()];
            $responseCode = 500;
        }

        return $result->setHttpResponseCode($responseCode)->setData($responseData);
    }

    /**
     * Create CSRF validation exception.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        /** @var ResponseHttp $response */
        $response = $this->context->getResponse();
        $response->setHttpResponseCode(403);
        $response->setReasonPhrase($this->errorMessage);

        return new InvalidRequestException($response);
    }

    /**
     * Validate for CSRF.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $header = $request->getHeader('MONEI-Signature');
        if (!\is_array($header)) {
            $header = $this->splitMoneiSignature((string) $header);
        }
        $body = $request->getContent();
        $this->logger->debug('[Callback request received]');
        $this->logger->debug('[Header] ' . json_encode(json_decode($this->serializer->serialize($header)), JSON_PRETTY_PRINT));
        $this->logger->debug('[Body] ' . json_encode(json_decode($body), JSON_PRETTY_PRINT));

        try {
            $this->verifySignature($body, $header);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical('[Signature verification failed] ' . $e->getMessage());
            $this->logger->critical('[Request body] ' . ($body ?? 'not available'));

            return false;
        }

        return true;
    }

    /**
     * Verifies signature from header.
     *
     * @param string $body
     * @param array $header
     *
     * @throws LocalizedException
     */
    private function verifySignature(string $body, array $header): void
    {
        $hmac = hash_hmac('SHA256', $header['t'] . '.' . $body, $this->getApiKey());

        if ($hmac !== $header['v1']) {
            throw new LocalizedException(__('Callback signature verification failed'));
        }
    }

    /**
     * Get webservice API key(test or production).
     *
     * @throws NoSuchEntityException
     */
    private function getApiKey(): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getApiKey($currentStoreId);
    }

    /**
     * Split Monei signature into associative array.
     *
     * @param string $signature
     */
    private function splitMoneiSignature(string $signature): array
    {
        return array_reduce(explode(',', $signature), function ($result, $part) {
            [$key, $value] = explode('=', $part);
            $result[$key] = $value;

            return $result;
        }, []);
    }
}

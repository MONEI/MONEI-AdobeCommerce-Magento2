<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
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
use Exception;

/**
 * Controller for managing callback from Monei system
 */
class Callback implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @var string
     */
    private string $errorMessage = '';

    /**
     * @var Context
     */
    private Context $context;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var GenerateInvoiceInterface
     */
    private GenerateInvoiceInterface $generateInvoiceService;

    /**
     * @var SetOrderStatusAndStateInterface
     */
    private SetOrderStatusAndStateInterface $setOrderStatusAndStateService;

    /**
     * @var MagentoRedirect
     */
    private MagentoRedirect $resultRedirectFactory;

    /**
     * @param Context $context
     * @param SerializerInterface $serializer
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param GenerateInvoiceInterface $generateInvoiceService
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService
     * @param MagentoRedirect $resultRedirectFactory
     */
    public function __construct(
        Context $context,
        SerializerInterface $serializer,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger,
        StoreManagerInterface $storeManager,
        GenerateInvoiceInterface $generateInvoiceService,
        SetOrderStatusAndStateInterface $setOrderStatusAndStateService,
        MagentoRedirect $resultRedirectFactory
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->setOrderStatusAndStateService = $setOrderStatusAndStateService;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->serializer = $serializer;
        $this->context = $context;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $content = $this->context->getRequest()->getContent();
        $body = $this->serializer->unserialize($content);

        // Log the entire body for debugging purposes
        $this->logger->debug('[Callback controller]');
        $this->logger->debug('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));

        if (isset($body['orderId'], $body['status'])) {
            if ($body['status'] === Monei::ORDER_STATUS_SUCCEEDED) {
                $this->generateInvoiceService->execute($body);
            }
            $this->setOrderStatusAndStateService->execute($body);
        } else {
            $this->logger->critical('[Callback error] Request failed');
            $this->logger->critical('[Request body] ' . json_encode(json_decode($content), JSON_PRETTY_PRINT));
        }

        return $this->resultRedirectFactory->setPath('/');
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
        $response = $this->context->getResponse();
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
        $header = $request->getHeader('MONEI-Signature');
        if (!is_array($header)) {
            $header = $this->splitMoneiSignature((string) $header);
        }
        $body = $request->getContent();
        $this->logger->debug('[Callback validation]');
        $this->logger->debug('[Signature header] ' . json_encode($header, JSON_PRETTY_PRINT));
        $this->logger->debug('[Request body] ' . json_encode(json_decode($body), JSON_PRETTY_PRINT));

        try {
            $this->verifySignature($body, $header);
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical('[Signature verification error] ' . $e->getMessage());
            $this->logger->critical('[Request body] ' . json_encode(json_decode($body), JSON_PRETTY_PRINT));

            return false;
        }

        return true;
    }

    /**
     * Verifies signature from header
     *
     * @param string $body
     * @param array $header
     * @throws LocalizedException
     * @return void
     */
    private function verifySignature(string $body, array $header): void
    {
        $hmac = hash_hmac('SHA256', $header['t'] . '.' . $body, $this->getApiKey());

        if ($hmac !== $header['v1']) {
            throw new LocalizedException(__('Callback signature verification failed'));
        }
    }

    /**
     * Get webservice API key(test or production)
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getApiKey(): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getApiKey($currentStoreId);
    }

    /**
     * Split Monei signature into components
     *
     * @param string $signature
     * @return array
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

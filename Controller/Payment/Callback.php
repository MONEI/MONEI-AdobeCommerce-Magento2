<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Exception;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;

/**
 * Controller for managing callback from Monei system
 */
class Callback implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GenerateInvoiceInterface
     */
    private $generateInvoiceService;

    /**
     * @var SetOrderStatusAndStateInterface
     */
    private $setOrderStatusAndStateService;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MagentoRedirect
     */
    private $resultRedirectFactory;

    /**
     * @var string
     */
    private string $errorMessage = '';

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
        $this->context = $context;
        $this->serializer = $serializer;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->setOrderStatusAndStateService = $setOrderStatusAndStateService;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $body = $this->context->getRequest()->getParams();
        if (isset($body['orderId']) && isset($body['status'])) {
            if ($body['status'] === Monei::ORDER_STATUS_SUCCEEDED) {
                $this->generateInvoiceService->execute($body);
            }
            $this->setOrderStatusAndStateService->execute($body);
        } else {
            $this->logger->critical('Callback request failed.');
            $this->logger->critical('Request body: ' . $this->serializer->serialize($body));
        }

        return $this->resultRedirectFactory->setPath('/');
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $header = $this->serializer->unserialize($this->context->getRequest()->getHeader('Monei-Signature'));
        $body = $request->getParams();
        try {
            $this->verifySignature($body, $header);
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->logger->critical($e->getMessage());
            $this->logger->critical('Request body: ' . $this->serializer->serialize($body));

            return false;
        }

        return true;
    }

    /**
     * Verifies signature from header
     *
     * @param array $body
     * @param array $header
     * @throws LocalizedException
     */
    private function verifySignature(array $body, array $header): void
    {
        $body = str_replace('\/', '/', $this->serializer->serialize($body));
        $hmac = hash_hmac('SHA256', $header['t'] . '.' . $body, $this->getApiKey());

        if ($hmac !== $header['v1']) {
            throw new LocalizedException(__('Callback signature verification failed'));
        }
    }

    /**
     * Get webservice API key(test or production)
     *
     * @return string
     */
    private function getApiKey(): string
    {
        $currentStoreId = $this->storeManager->getStore()->getId();
        if ($this->moduleConfig->getMode($currentStoreId) === Mode::MODE_TEST) {
            return $this->moduleConfig->getTestApiKey($currentStoreId);
        }

        return $this->moduleConfig->getProductionApiKey($currentStoreId);
    }
}

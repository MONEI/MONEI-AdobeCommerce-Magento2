<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Controller\Payment;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
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
    private string $errorMessage = '';
    private Context $context;
    private SerializerInterface $serializer;
    private MoneiPaymentModuleConfigInterface $moduleConfig;
    private Logger $logger;
    private StoreManagerInterface $storeManager;
    private GenerateInvoiceInterface $generateInvoiceService;
    private SetOrderStatusAndStateInterface $setOrderStatusAndStateService;
    private MagentoRedirect $resultRedirectFactory;

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
     * @inheritDoc
     */
    public function execute()
    {
        $content = $this->context->getRequest()->getContent();
        $body = $this->serializer->unserialize($content);
        if (isset($body['orderId'], $body['status'])) {
            if ($body['status'] === Monei::ORDER_STATUS_SUCCEEDED) {
                $this->generateInvoiceService->execute($body);
            }
            $this->setOrderStatusAndStateService->execute($body);
        } else {
            $this->logger->critical('Callback request failed.');
            $this->logger->critical('Request body: ' . $content);
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
        $header = $request->getHeader('MONEI-Signature');
        if(!is_array($header)){
            $header = $this->splitMoneiSignature((string)$header);
        }
        $body = $request->getContent();
        $this->logger->debug('Callback request received.');
        $this->logger->debug('Header:' . $this->serializer->serialize($header));
        $this->logger->debug('Body:' . $body);

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
     * @param string $body
     * @param array $header
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

    private function splitMoneiSignature(string $signature): array
    {
        return array_reduce(explode(',', $signature), function ($result, $part) {
            [$key, $value] = explode('=', $part);
            $result[$key] = $value;
            return $result;
        }, []);
    }
}

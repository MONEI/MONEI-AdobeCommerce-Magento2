<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;

/**
 * Monei WS service abstract class.
 */
abstract class AbstractService
{
    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    private ModuleVersion $moduleVersion;

    public function __construct(
        ClientFactory $clientFactory,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        SerializerInterface $serializer,
        Logger $logger,
        ModuleVersion $moduleVersion
    ) {
        $this->clientFactory = $clientFactory;
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->moduleVersion = $moduleVersion;
    }

    /**
     * Creates client with base URI.
     *
     * @throws NoSuchEntityException
     */
    public function createClient(?int $storeId = null): Client
    {
        return $this->clientFactory->create(['config' => [
            'base_uri' => $this->getApiUrl($storeId),
        ]]);
    }

    /**
     * Get webservice API url(test or production).
     *
     * @throws NoSuchEntityException
     */
    protected function getApiUrl(?int $storeId = null): string
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getUrl($currentStoreId);
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function getHeaders(?int $storeId = null): array
    {
        return [
            'Authorization' => $this->getApiKey($storeId),
            'User-Agent' => $this->getUserAgent(),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Throws exception about missing required argument.
     *
     * @throws LocalizedException
     */
    protected function throwRequiredArgumentException(string $parameter): void
    {
        throw new LocalizedException(__('Required parameter is missing %1', $parameter));
    }

    /**
     * Get array of URLs for WS request.
     */
    protected function getUrls(): array
    {
        return [
            'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
            'callbackUrl' => $this->urlBuilder->getUrl('monei/payment/callback/'),
            'cancelUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
            'failUrl' => $this->urlBuilder->getUrl('monei/payment/fail'),
        ];
    }

    /**
     * Get webservice API key(test or production).
     *
     * @throws NoSuchEntityException
     */
    private function getApiKey(?int $storeId = null): string
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getApiKey($currentStoreId);
    }

    /**
     * Get webservice API user agent with module version.
     */
    private function getUserAgent(): string
    {
        $moduleVersion = $this->moduleVersion->getModuleVersion();
        if ($moduleVersion) {
            return 'MONEI/Magento2/'.$moduleVersion;
        }

        return '';
    }
}

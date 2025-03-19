<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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
     * Module configuration provider.
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    protected $moduleConfig;

    /**
     * Magento store manager service.
     *
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Service for JSON serialization and deserialization.
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * Service for logging operations.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * HTTP client factory for creating API clients.
     *
     * @var ClientFactory
     *
     * @phpstan-var \GuzzleHttp\ClientFactory
     */
    private $clientFactory;

    /**
     * Magento URL builder service for generating URLs.
     *
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Module version provider.
     *
     * @var ModuleVersion
     */
    private ModuleVersion $moduleVersion;

    /**
     * Constructor for AbstractService.
     *
     * @param ClientFactory $clientFactory HTTP client factory
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration provider
     * @param StoreManagerInterface $storeManager Magento store manager
     * @param UrlInterface $urlBuilder URL builder service
     * @param SerializerInterface $serializer JSON serializer
     * @param Logger $logger Logging service
     * @param ModuleVersion $moduleVersion Module version provider
     */
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
     * @param ?int $storeId
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
     * Gets the API URL for the specified store.
     *
     * @param ?int $storeId Optional store ID, uses current store if not provided
     *
     * @throws NoSuchEntityException If the store cannot be found
     *
     * @return string The API URL
     */
    protected function getApiUrl(?int $storeId = null): string
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();

        return $this->moduleConfig->getUrl($currentStoreId);
    }

    /**
     * Gets the API headers for the specified store.
     *
     * @param ?int $storeId Optional store ID, uses current store if not provided
     *
     * @throws NoSuchEntityException If the store cannot be found
     *
     * @return array The API headers
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
     * @param string $parameter
     *
     * @throws LocalizedException
     */
    protected function throwRequiredArgumentException(string $parameter): void
    {
        throw new LocalizedException(
            __('Required parameter "%1" is missing or empty.', $parameter)
        );
    }

    /**
     * Get array of URLs for WS request.
     */
    protected function getUrls(): array
    {
        return [
            'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
            'callbackUrl' => $this->urlBuilder->getUrl('monei/payment/callback/'),
        ];
    }

    /**
     * Get webservice API key(test or production).
     *
     * @param ?int $storeId
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
            return 'MONEI/Magento2/' . $moduleVersion;
        }

        return '';
    }
}

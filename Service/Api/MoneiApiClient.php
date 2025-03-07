<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiClient;

/**
 * Client factory for MONEI Payment Gateway SDK
 *
 * This class provides initialization and configuration for the MONEI PHP SDK,
 * handling store-specific configuration and caching SDK instances.
 */
class MoneiApiClient
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ModuleVersion
     */
    private $moduleVersion;

    /**
     * @var array
     */
    private $instances = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     * @param ModuleVersion $moduleVersion
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger,
        ModuleVersion $moduleVersion
    ) {
        $this->storeManager = $storeManager;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
        $this->moduleVersion = $moduleVersion;
    }

    /**
     * Get or create instance of MONEI SDK
     *
     * @param int|null $storeId
     * @return MoneiClient
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getMoneiSdk(?int $storeId = null): MoneiClient
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();
        $cacheKey = (string)$currentStoreId;

        if (!isset($this->instances[$cacheKey])) {
            $apiKey = $this->getApiKey($currentStoreId);

            // Initialize the MoneiClient from the SDK
            $monei = new MoneiClient($apiKey);

            // Set custom User-Agent header to identify the integration
            $monei->setUserAgent('MONEI/Magento2/' . $this->moduleVersion->getModuleVersion());

            $this->instances[$cacheKey] = $monei;
        }

        return $this->instances[$cacheKey];
    }

    /**
     * Convert response object to array
     *
     * @param mixed $response
     * @return array
     */
    public function convertResponseToArray($response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        if (method_exists($response, 'jsonSerialize')) {
            return $response->jsonSerialize();
        }

        // Last resort - should rarely be needed
        return (array)$response;
    }

    /**
     * Get MONEI API URL based on the sandbox mode setting
     *
     * @param int $storeId
     * @return string
     */
    private function getApiUrl(int $storeId): string
    {
        $isTestMode = $this->moduleConfig->getMode($storeId) === 0; // 0 = Test, 1 = Production
        return $isTestMode
            ? $this->moduleConfig->getTestUrl($storeId)
            : $this->moduleConfig->getProductionUrl($storeId);
    }

    /**
     * Get API key based on sandbox mode
     *
     * @param int $storeId
     * @return string
     * @throws LocalizedException
     */
    private function getApiKey(int $storeId): string
    {
        $isTestMode = $this->moduleConfig->getMode($storeId) === 0; // 0 = Test, 1 = Production
        $apiKey = $isTestMode
            ? $this->moduleConfig->getTestApiKey($storeId)
            : $this->moduleConfig->getProductionApiKey($storeId);

        if (empty($apiKey)) {
            throw new LocalizedException(
                __('MONEI API key is not configured. Please set it in the module configuration.')
            );
        }

        return $apiKey;
    }
}

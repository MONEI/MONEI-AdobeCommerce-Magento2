<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
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
     * Store manager to access current store
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Module configuration provider
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * Logger for API operations
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Module version provider
     *
     * @var ModuleVersion
     */
    private ModuleVersion $moduleVersion;

    /**
     * Cache of SDK instances by store ID
     *
     * @var array<string, MoneiClient>
     */
    private array $instances = [];

    /**
     * @param StoreManagerInterface $storeManager Store manager to access current store
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration provider
     * @param Logger $logger Logger for API operations
     * @param ModuleVersion $moduleVersion Module version provider
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
     * @param int|null $storeId Store ID to use for configuration, defaults to current store
     * @return MoneiClient Initialized MONEI SDK client
     * @throws LocalizedException If the API key is not configured
     * @throws NoSuchEntityException If the store doesn't exist
     */
    public function getMoneiSdk(?int $storeId = null): MoneiClient
    {
        $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();
        $cacheKey = (string) $currentStoreId;

        if (!isset($this->instances[$cacheKey])) {
            try {
                $apiKey = $this->getApiKey((int) $currentStoreId);

                // Initialize the MoneiClient from the SDK
                $monei = new MoneiClient($apiKey);

                // Set custom User-Agent header to identify the integration
                $monei->setUserAgent('MONEI/Magento2/' . $this->moduleVersion->getModuleVersion());

                // Store the SDK instance in cache
                $this->instances[$cacheKey] = $monei;

                $this->logger->debug('SDK initialized', ['store_id' => $currentStoreId]);
            } catch (LocalizedException $e) {
                $this->logger->logApiError('initSdk', $e->getMessage(), [
                    'store_id' => $currentStoreId
                ]);

                throw $e;
            } catch (ApiException $e) {
                $errorBody = $e->getResponseBody() ? json_decode($e->getResponseBody(), true) : null;
                $errorMessage = $errorBody['message'] ?? $e->getMessage();
                $statusCode = $e->getCode();

                $this->logger->logApiError('initSdk', 'API error during initialization', [
                    'store_id' => $currentStoreId,
                    'status_code' => $statusCode,
                    'error_message' => $errorMessage
                ]);

                throw new LocalizedException(
                    __('Authentication error: Please check your MONEI API credentials')
                );
            } catch (\Exception $e) {
                $this->logger->logApiError('initSdk', 'Unexpected error', [
                    'store_id' => $currentStoreId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ]);

                throw new LocalizedException(
                    __('Failed to initialize MONEI payment gateway: %1', $e->getMessage())
                );
            }
        }

        return $this->instances[$cacheKey];
    }

    /**
     * Reset SDK instance for a specific store
     *
     * This can be useful if store configuration changes during the request.
     *
     * @param int|null $storeId Store ID to reset, defaults to all stores
     * @return void
     */
    public function resetSdkInstance(?int $storeId = null): void
    {
        if ($storeId !== null) {
            $key = (string) $storeId;
            $this->removeInstanceByKey($key);

            return;
        }

        // Reset all instances
        $this->instances = [];
    }

    /**
     * Reinitialize the SDK with a specific API key
     *
     * This method is useful when the API key has just been updated in the configuration
     * and we need to ensure the SDK uses the new key immediately.
     *
     * @param string $apiKey The API key to use for reinitialization
     * @param int|null $storeId Store ID to reinitialize, defaults to current store
     * @return MoneiClient The reinitialized SDK client
     * @throws LocalizedException If initialization fails
     * @throws NoSuchEntityException If the store doesn't exist
     */
    public function reinitialize(string $apiKey, ?int $storeId = null): MoneiClient
    {
        if (empty($apiKey)) {
            throw new LocalizedException(
                __('Cannot reinitialize MONEI SDK with an empty API key')
            );
        }

        try {
            $currentStoreId = $storeId ?: $this->storeManager->getStore()->getId();
            $cacheKey = (string) $currentStoreId;

            // Reset any existing instance
            $this->removeInstanceByKey($cacheKey);

            // Initialize the MoneiClient from the SDK with the provided API key
            $monei = new MoneiClient($apiKey);

            // Set custom User-Agent header to identify the integration
            $monei->setUserAgent('MONEI/Magento2/' . $this->moduleVersion->getModuleVersion());

            // Store the SDK instance in cache
            $this->instances[$cacheKey] = $monei;

            $this->logger->debug('SDK reinitialized with provided API key', [
                'store_id' => $currentStoreId
            ]);

            return $monei;
        } catch (ApiException $e) {
            $errorBody = $e->getResponseBody() ? json_decode($e->getResponseBody(), true) : null;
            $errorMessage = $errorBody['message'] ?? $e->getMessage();
            $statusCode = $e->getCode();

            $this->logger->logApiError('reinitSdk', 'API error during reinitialization', [
                'status_code' => $statusCode,
                'error_message' => $errorMessage
            ]);

            throw new LocalizedException(
                __('Authentication error during reinitialization: Please check your MONEI API credentials')
            );
        } catch (\Exception $e) {
            $this->logger->logApiError('reinitSdk', 'Unexpected error during reinitialization', [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);

            throw new LocalizedException(
                __('Failed to reinitialize MONEI payment gateway: %1', $e->getMessage())
            );
        }
    }

    /**
     * Remove a SDK instance by its key
     *
     * @param string $key The key for the instance to remove
     * @return void
     */
    private function removeInstanceByKey(string $key): void
    {
        if (array_key_exists($key, $this->instances)) {
            unset($this->instances[$key]);
        }
    }

    /**
     * Convert response object to array
     *
     * Handles different types of API responses and converts them to arrays
     * for consistent handling.
     *
     * @param mixed $response API response to convert
     * @return array Response data as array
     */
    public function convertResponseToArray($response): array
    {
        // Already an array
        if (is_array($response)) {
            return $response;
        }

        // Null response
        if ($response === null) {
            return [];
        }

        try {
            // For SDK models, use the jsonSerialize method directly
            if (method_exists($response, 'jsonSerialize')) {
                return (array) $response->jsonSerialize();
            }

            // Fallback for any other object types
            if (is_object($response)) {
                return json_decode(json_encode($response), true) ?: [];
            }

            // For any other types, return empty array
            return [];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to convert response to array', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'response_type' => gettype($response),
                'response_class' => is_object($response) ? get_class($response) : null
            ]);

            return [];
        }
    }

    /**
     * Get API key based on store's sandbox mode setting
     *
     * @param int $storeId Store ID to get API key for
     * @return string API key for the specified store
     * @throws LocalizedException If the API key is not configured
     */
    private function getApiKey(int $storeId): string
    {
        $isTestMode = $this->moduleConfig->getMode($storeId) === 1;  // 1 = Test, 0 = Production

        $apiKey = $isTestMode
            ? $this->moduleConfig->getTestApiKey($storeId)
            : $this->moduleConfig->getProductionApiKey($storeId);

        // Log configuration details in debug mode only
        $this->logger->debug('API configuration loaded', [
            'store_id' => $storeId,
            'mode' => $isTestMode ? 'Test' : 'Production',
            'api_key_configured' => !empty($apiKey)
        ]);

        if (empty($apiKey)) {
            throw new LocalizedException(
                __('MONEI API key is not configured. Please set it in the module configuration.')
            );
        }

        return $apiKey;
    }
}

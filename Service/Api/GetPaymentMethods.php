<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;

/**
 * Monei get payment methods service class using the official MONEI PHP SDK.
 *
 * Retrieves available payment methods from the MONEI API based on account information.
 */
class GetPaymentMethods extends AbstractApiService implements GetPaymentMethodsInterface
{
    /**
     * Module configuration provider
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * Account ID registry
     *
     * @var RegistryAccountId
     */
    private RegistryAccountId $registryAccountId;

    /**
     * Static cache of payment methods to avoid repeated API calls within a single request
     *
     * @var array<string, array{data: PaymentMethods, timestamp: int}>
     */
    private static array $paymentMethodsCache = [];

    /**
     * Cache lifetime in seconds (1 minute)
     */
    private const CACHE_LIFETIME = 60;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient $apiClient API client factory for MONEI SDK
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param RegistryAccountId $registryAccountId
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        RegistryAccountId $registryAccountId
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
        $this->moduleConfig = $moduleConfig;
        $this->registryAccountId = $registryAccountId;
    }

    /**
     * Get available payment methods using the official MONEI SDK
     *
     * @param string|null $accountId Optional account ID to filter payment methods
     *
     * @return PaymentMethods MONEI SDK payment methods object
     * @throws LocalizedException If payment methods cannot be retrieved
     */
    public function execute(string $accountId = null): PaymentMethods
    {
        // Determine account ID if not provided
        if ($accountId === null) {
            $storeId = null;
            $accountId = $this->moduleConfig->getAccountId($storeId);
        }

        // Create cache key based on account ID
        $cacheKey = $accountId ?: 'default';
        $currentTime = time();

        // Return from static cache if already fetched and not expired
        if (isset(self::$paymentMethodsCache[$cacheKey]) &&
                ($currentTime - self::$paymentMethodsCache[$cacheKey]['timestamp'] < self::CACHE_LIFETIME)) {
            $this->logger->debug('[ApiPaymentMethods] Using cached payment methods', ['accountId' => $accountId]);
            return self::$paymentMethodsCache[$cacheKey]['data'];
        }

        // Store the account ID in registry for later use if not empty
        if (!empty($accountId)) {
            $this->registryAccountId->set($accountId);
        }

        // Use the standardized SDK call pattern
        $result = $this->executeMoneiSdkCall(
            'getPaymentMethods',
            function (MoneiClient $moneiSdk) use ($accountId) {
                // When using Account ID, set it on the SDK
                if (!empty($accountId)) {
                    $moneiSdk->setAccountId($accountId);
                }

                // Get the payment methods
                return $moneiSdk->paymentMethods->get($accountId);
            },
            ['accountId' => $accountId]
        );

        // Store in static cache with timestamp
        self::$paymentMethodsCache[$cacheKey] = [
            'data' => $result,
            'timestamp' => $currentTime
        ];

        return $result;
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiBizumPaymentModuleConfigInterface;

/**
 * Get Monei payment method configuration class.
 */
class MoneiBizumPaymentModuleConfig implements MoneiBizumPaymentModuleConfigInterface
{
    /**
     * Scope configuration for accessing store configuration values.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Constructor for MoneiBizumPaymentModuleConfig.
     *
     * @param ScopeConfigInterface $scopeConfig The configuration interface for accessing store configuration values
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the Monei Bizum payment method is enabled.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return bool True if the payment method is enabled, false otherwise
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_PAYMENT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the title of the Monei Bizum payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The payment method title
     */
    public function getTitle(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if the payment method is restricted to specific countries.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return bool True if the payment method is restricted to specific countries
     */
    public function isAllowSpecific(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::ALLOW_SPECIFIC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the list of specific countries where the payment method is available.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string Comma-separated list of country codes
     */
    public function getSpecificCountries(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SPECIFIC_COUNTRIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the sort order for the Monei Bizum payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return int The sort order value
     */
    public function getSortOrder(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::SORT_ORDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the JSON style configuration for the Monei Bizum payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return array The JSON style configuration as an array
     */
    public function getJsonStyle(?int $storeId = null): array
    {
        $result = (string) $this->scopeConfig->getValue(
            self::JSON_STYLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $result ? json_decode($result, true) : [];
    }
}

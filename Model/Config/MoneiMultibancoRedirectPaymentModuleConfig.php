<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiMultibancoRedirectPaymentModuleConfigInterface;

/**
 * Monei Multibanco Redirect payment method configuration provider.
 */
class MoneiMultibancoRedirectPaymentModuleConfig implements MoneiMultibancoRedirectPaymentModuleConfigInterface
{
    /**
     * Scope configuration for accessing store configuration values.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Constructor for MoneiMultibancoRedirectPaymentModuleConfig.
     *
     * @param ScopeConfigInterface $scopeConfig The configuration interface for accessing store configuration values
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the MONEI Multibanco Redirect payment method is enabled.
     *
     * @param int|null $storeId The store ID to check the configuration for
     *
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
     * Get the title of the MONEI Multibanco Redirect payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     *
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
     * Check if specific countries are allowed for the MONEI Multibanco Redirect payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     *
     * @return bool True if specific countries are allowed, false otherwise
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
     * Get the list of specific countries allowed for the MONEI Multibanco Redirect payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     *
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
     * Get the sort order for the MONEI Multibanco Redirect payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     *
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
}

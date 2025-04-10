<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiMBWayRedirectPaymentModuleConfigInterface;

/**
 * Monei MBWay Redirect payment method configuration provider.
 */
class MoneiMBWayRedirectPaymentModuleConfig implements MoneiMBWayRedirectPaymentModuleConfigInterface
{
    /**
     * Scope configuration for accessing store configuration values.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Constructor for MoneiMBWayRedirectPaymentModuleConfig.
     *
     * @param ScopeConfigInterface $scopeConfig The configuration interface for accessing store configuration values
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the MONEI MBWay Redirect payment method is enabled.
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
     * Get the title of the MONEI MBWay Redirect payment method.
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
     * Check if specific countries are allowed for the MONEI MBWay Redirect payment method.
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
     * Get the list of specific countries allowed for the MONEI MBWay Redirect payment method.
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
     * Get the sort order for the MONEI MBWay Redirect payment method.
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

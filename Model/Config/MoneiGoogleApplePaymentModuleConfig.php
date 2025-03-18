<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;

/**
 * Monei Google Pay and Apple Pay payment method configuration.
 *
 * This class provides access to configuration values for the Google Pay and Apple Pay
 * payment methods provided by Monei, including enabled status, titles, country restrictions,
 * sort order, and styling options.
 */
class MoneiGoogleApplePaymentModuleConfig implements MoneiGoogleApplePaymentModuleConfigInterface
{
    /**
     * Scope configuration.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Constructor for MoneiGoogleApplePaymentModuleConfig.
     *
     * @param ScopeConfigInterface $scopeConfig The configuration interface for accessing store configuration values
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the Monei Google Pay and Apple Pay payment methods are enabled.
     *
     * @param int|null $storeId Store ID to check configuration for
     *
     * @return bool True if enabled, false otherwise
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
     * Get the title for the Monei Google Pay and Apple Pay payment methods.
     *
     * @param int|null $storeId Store ID to get configuration for
     *
     * @return string Payment method title
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
     * Get the Google Pay specific title.
     *
     * @param int|null $storeId Store ID to get configuration for
     *
     * @return string Google Pay title
     */
    public function getGoogleTitle(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::GOOGLE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the Apple Pay specific title.
     *
     * @param int|null $storeId Store ID to get configuration for
     *
     * @return string Apple Pay title
     */
    public function getAppleTitle(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::APPLE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if payment is restricted to specific countries.
     *
     * @param int|null $storeId Store ID to get configuration for
     *
     * @return bool True if restricted to specific countries, false otherwise
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
     * Get comma-separated list of specific country codes allowed for payment.
     *
     * @param int|null $storeId Store ID to get configuration for
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
     * Get the sort order for the payment method.
     *
     * @param int|null $storeId Store ID to get configuration for
     *
     * @return int Sort order value
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
     * Get the JSON style configuration for the payment method.
     *
     * @param int|null $storeId Store ID to get configuration for
     *
     * @return array Style configuration as an array
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

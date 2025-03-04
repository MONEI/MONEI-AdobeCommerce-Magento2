<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\Language;
use Monei\MoneiPayment\Model\Config\Source\Mode;

/**
 * Monei payment method configuration provider.
 *
 * This class provides access to configuration values for the Monei payment method,
 * including API credentials, environment settings, display options, payment statuses,
 * and country restrictions.
 */
class MoneiPaymentModuleConfig implements MoneiPaymentModuleConfigInterface
{
    /**
     * Scope configuration for accessing store configuration values.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Constructor for MoneiPaymentModuleConfig.
     *
     * @param ScopeConfigInterface $scopeConfig The configuration interface for accessing store configuration values
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the Monei payment method is enabled.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return bool True if the payment method is enabled, false otherwise
     */
    public function isEnabled($storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_PAYMENT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the mode (test or production) for the Monei payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return int The mode value (1 for test, 2 for production)
     */
    public function getMode($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the appropriate URL based on the current mode.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The URL for the current mode
     */
    public function getUrl($storeId = null): string
    {
        return Mode::MODE_TEST === $this->getMode($storeId)
            ? $this->getTestUrl($storeId)
            : $this->getProductionUrl($storeId);
    }

    /**
     * Get the test environment URL.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The test URL
     */
    public function getTestUrl($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TEST_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the production environment URL.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The production URL
     */
    public function getProductionUrl($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRODUCTION_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the appropriate account ID based on the current mode.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The account ID for the current mode
     */
    public function getAccountId(?int $storeId = null): string
    {
        return Mode::MODE_TEST === $this->getMode($storeId)
            ? $this->getTestAccountId($storeId)
            : $this->getProductionAccountId($storeId);
    }

    /**
     * Get the test environment account ID.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The test account ID
     */
    public function getTestAccountId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TEST_ACCOUNT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the production environment account ID.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The production account ID
     */
    public function getProductionAccountId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRODUCTION_ACCOUNT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the appropriate API key based on the current mode.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The API key for the current mode
     */
    public function getApiKey($storeId = null): string
    {
        return Mode::MODE_TEST === $this->getMode($storeId)
            ? $this->getTestApiKey($storeId)
            : $this->getProductionApiKey($storeId);
    }

    /**
     * Get the test environment API key.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The test API key
     */
    public function getTestApiKey($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TEST_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the production environment API key.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The production API key
     */
    public function getProductionApiKey($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRODUCTION_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the language for the Monei payment interface.
     *
     * First tries to use the store's locale language if supported by Monei,
     * otherwise falls back to the configured language.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The language code
     */
    public function getLanguage(?int $storeId = null): string
    {
        // First, try to get the store's locale code
        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // Extract the language part from the locale (e.g., 'en_US' => 'en')
        $localeLanguage = substr($locale, 0, 2);

        // Check if the extracted language is supported by Monei
        $supportedLanguages = array_keys(Language::LANGUAGES);

        // If the locale language is supported, use it
        if (in_array(strtolower($localeLanguage), $supportedLanguages)) {
            return strtolower($localeLanguage);
        }

        // Otherwise, fall back to the configured value
        return (string) $this->scopeConfig->getValue(
            self::LANGUAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the title of the Monei payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The payment method title
     */
    public function getTitle($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the description of the Monei payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The payment method description
     */
    public function getDescription($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the type of payment for the Monei payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return int The payment type value
     */
    public function getTypeOfPayment($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::TYPE_OF_PAYMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the order status to be set when a payment is confirmed.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The confirmed status code
     */
    public function getConfirmedStatus($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIRMED_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the order status to be set when a payment is pre-authorized.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return string The pre-authorized status code
     */
    public function getPreAuthorizedStatus($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRE_AUTHORIZED_STATUS,
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
    public function isAllowSpecific($storeId = null): bool
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
    public function getSpecificCountries($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SPECIFIC_COUNTRIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the sort order for the Monei payment method.
     *
     * @param int|null $storeId The store ID to check the configuration for
     * @return int The sort order value
     */
    public function getSortOrder($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::SORT_ORDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}

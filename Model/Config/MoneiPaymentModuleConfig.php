<?php

/**
 * @author Monei Team
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

    public function isEnabled($storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_PAYMENT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMode($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getUrl($storeId = null): string
    {
        return Mode::MODE_TEST === $this->getMode($storeId)
            ? $this->getTestUrl($storeId)
            : $this->getProductionUrl($storeId);
    }

    public function getTestUrl($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TEST_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductionUrl($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRODUCTION_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAccountId(?int $storeId = null): string
    {
        return Mode::MODE_TEST === $this->getMode($storeId)
            ? $this->getTestAccountId($storeId)
            : $this->getProductionAccountId($storeId);
    }

    public function getTestAccountId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TEST_ACCOUNT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductionAccountId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRODUCTION_ACCOUNT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getApiKey($storeId = null): string
    {
        return Mode::MODE_TEST === $this->getMode($storeId)
            ? $this->getTestApiKey($storeId)
            : $this->getProductionApiKey($storeId);
    }

    public function getTestApiKey($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TEST_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductionApiKey($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRODUCTION_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

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

    public function getTitle($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDescription($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTypeOfPayment($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::TYPE_OF_PAYMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getConfirmedStatus($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIRMED_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getPreAuthorizedStatus($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::PRE_AUTHORIZED_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAllowSpecific($storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::ALLOW_SPECIFIC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSpecificCountries($storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SPECIFIC_COUNTRIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSortOrder($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::SORT_ORDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}

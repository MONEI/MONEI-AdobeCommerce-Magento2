<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Get Monei payment method configuration class.
 */
class MoneiGoogleApplePaymentModuleConfig implements MoneiGoogleApplePaymentModuleConfigInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_PAYMENT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getTitle(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getGoogleTitle(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::GOOGLE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getAppleTitle(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::APPLE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function isAllowSpecific(int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::ALLOW_SPECIFIC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getSpecificCountries(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SPECIFIC_COUNTRIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::SORT_ORDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;

/**
 * Get Monei payment method configuration class.
 */
class MoneiCardPaymentModuleConfig implements MoneiCardPaymentModuleConfigInterface
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_PAYMENT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTitle(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isEnabledTokenization(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_ENABLED_TOKENIZATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAllowSpecific(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::ALLOW_SPECIFIC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSpecificCountries(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::SPECIFIC_COUNTRIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSortOrder(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::SORT_ORDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

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

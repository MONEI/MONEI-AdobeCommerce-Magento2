<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Get Monei payment method configuration class.
 */
class MoneiPaymentModuleConfig implements MoneiPaymentModuleConfigInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function getTypeOfConnection(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::TYPE_OF_CONNECTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function isEnabledTokenization(int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::IS_ENABLED_TOKENIZATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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

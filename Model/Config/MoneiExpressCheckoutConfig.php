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
use Monei\MoneiPayment\Api\Config\MoneiExpressCheckoutConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaypalPaymentModuleConfigInterface;

/**
 * Express checkout configuration.
 */
class MoneiExpressCheckoutConfig implements MoneiExpressCheckoutConfigInterface
{
    /**
     * Scope configuration for accessing store configuration values.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var MoneiPaypalPaymentModuleConfigInterface
     */
    private MoneiPaypalPaymentModuleConfigInterface $paypalConfig;

    /**
     * Per-surface configuration paths.
     *
     * @var array<string, string>
     */
    private const LOCATION_PATHS = [
        self::LOCATION_PRODUCT => self::ENABLED_ON_PRODUCT,
        self::LOCATION_CART => self::ENABLED_ON_CART,
        self::LOCATION_MINICART => self::ENABLED_ON_MINICART,
        self::LOCATION_CHECKOUT => self::ENABLED_ON_CHECKOUT,
    ];

    /**
     * Constructor for MoneiExpressCheckoutConfig.
     *
     * @param ScopeConfigInterface $scopeConfig The configuration interface for accessing store configuration values
     * @param MoneiPaypalPaymentModuleConfigInterface $paypalConfig PayPal payment method configuration
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        MoneiPaypalPaymentModuleConfigInterface $paypalConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->paypalConfig = $paypalConfig;
    }

    /**
     * Whether express checkout is enabled at all.
     *
     * @param int|null $storeId The store ID to check the configuration for
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::IS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether express checkout is enabled on a given surface.
     *
     * An unknown location is treated as disabled rather than enabled: a typo in a
     * caller must not silently expose a payment button on a surface nobody chose.
     *
     * @param string   $location One of the LOCATION_* constants
     * @param int|null $storeId  The store ID to check the configuration for
     */
    public function isEnabledAt(string $location, ?int $storeId = null): bool
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }

        if (!isset(self::LOCATION_PATHS[$location])) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::LOCATION_PATHS[$location],
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function isPayPalEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->paypalConfig->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(
                self::PAYPAL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    /**
     * Style object passed to the wallet button.
     *
     * @param int|null $storeId The store ID to check the configuration for
     */
    public function getJsonStyle(?int $storeId = null): array
    {
        $result = (string) $this->scopeConfig->getValue(
            self::JSON_STYLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$result) {
            return [];
        }

        // A stored scalar such as "45" is valid JSON but not a style object, and
        // returning it would violate the array return type while Magento builds
        // the checkout config.
        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : [];
    }
}

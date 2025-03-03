<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Monei\MoneiPayment\Api\Config\AllMoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiBizumPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

/**
 * Configuration provider for all Monei payment methods.
 */
class AllMoneiPaymentModuleConfig implements AllMoneiPaymentModuleConfigInterface
{
    /**
     * Configuration for the main Monei payment method.
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig;

    /**
     * Configuration for the Monei card payment method.
     *
     * @var MoneiCardPaymentModuleConfigInterface
     */
    private MoneiCardPaymentModuleConfigInterface $moneiCardPaymentModuleConfig;

    /**
     * Configuration for the Monei Bizum payment method.
     *
     * @var MoneiBizumPaymentModuleConfigInterface
     */
    private MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig;

    /**
     * Configuration for the Monei Google/Apple Pay payment method.
     *
     * @var MoneiGoogleApplePaymentModuleConfigInterface
     */
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;

    /**
     * Constructor for AllMoneiPaymentModuleConfig.
     *
     * @param MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig
     *     Configuration for the main Monei payment method
     * @param MoneiCardPaymentModuleConfigInterface $moneiCardPaymentModuleConfig
     *     Configuration for the Monei card payment method
     * @param MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig
     *     Configuration for the Monei Bizum payment method
     * @param MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig
     *     Configuration for the Monei Google/Apple Pay payment method
     */
    public function __construct(
        MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig,
        MoneiCardPaymentModuleConfigInterface $moneiCardPaymentModuleConfig,
        MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig,
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig,
    ) {
        $this->moneiPaymentModuleConfig = $moneiPaymentModuleConfig;
        $this->moneiCardPaymentModuleConfig = $moneiCardPaymentModuleConfig;
        $this->moneiBizumPaymentModuleConfig = $moneiBizumPaymentModuleConfig;
        $this->moneiGoogleApplePaymentModuleConfig = $moneiGoogleApplePaymentModuleConfig;
    }

    /**
     * Check if any of the Monei payment methods are enabled.
     *
     * @param int|null $storeId The store ID to check the configuration for
     *
     * @return bool True if any payment method is enabled, false otherwise
     */
    public function isAnyPaymentEnabled($storeId = null): bool
    {
        return (bool) $this->moneiPaymentModuleConfig->isEnabled($storeId) ||
            (bool) $this->moneiCardPaymentModuleConfig->isEnabled($storeId) ||
            (bool) $this->moneiBizumPaymentModuleConfig->isEnabled($storeId) ||
            (bool) $this->moneiGoogleApplePaymentModuleConfig->isEnabled($storeId);
    }
}

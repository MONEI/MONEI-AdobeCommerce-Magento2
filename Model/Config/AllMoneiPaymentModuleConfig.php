<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config;

use Monei\MoneiPayment\Api\Config\AllMoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiBizumPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

class AllMoneiPaymentModuleConfig implements AllMoneiPaymentModuleConfigInterface
{
    private MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig;

    private MoneiCardPaymentModuleConfigInterface $moneiCardPaymentModuleConfig;

    private MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig;

    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;

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

    public function isAnyPaymentEnabled($storeId = null): bool
    {
        return (bool) $this->moneiPaymentModuleConfig->isEnabled($storeId)
            || (bool) $this->moneiCardPaymentModuleConfig->isEnabled($storeId)
            || (bool) $this->moneiBizumPaymentModuleConfig->isEnabled($storeId)
            || (bool) $this->moneiGoogleApplePaymentModuleConfig->isEnabled($storeId);
    }
}

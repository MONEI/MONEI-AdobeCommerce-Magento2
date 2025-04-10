<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Service class to check if Apple Pay is enabled in the Monei account.
 */
class ApplePayAvailability
{
    /**
     * Google and Apple Payment module configuration.
     *
     * @var MoneiGoogleApplePaymentModuleConfigInterface
     */
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;

    /**
     * Service to get available Monei payment methods.
     *
     * @var AvailablePaymentMethods
     */
    private AvailablePaymentMethods $availablePaymentMethods;

    /**
     * Constructor for ApplePayAvailability.
     *
     * @param MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig
     *        Configuration for Google and Apple Pay
     * @param AvailablePaymentMethods $availablePaymentMethods
     *        Service to retrieve available payment methods
     */
    public function __construct(
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig,
        AvailablePaymentMethods $availablePaymentMethods
    ) {
        $this->moneiGoogleApplePaymentModuleConfig = $moneiGoogleApplePaymentModuleConfig;
        $this->availablePaymentMethods = $availablePaymentMethods;
    }

    /**
     * Check if Apple Pay is enabled in the Monei account.
     *
     * @return bool True if Apple Pay is enabled, false otherwise
     */
    public function execute(): bool
    {
        if (!$this->moneiGoogleApplePaymentModuleConfig->isEnabled()) {
            return false;
        }
        $availableMoneiPaymentMethods = $this->availablePaymentMethods->execute();

        return \in_array(Monei::MONEI_APPLE_CODE, $availableMoneiPaymentMethods, true);
    }
}

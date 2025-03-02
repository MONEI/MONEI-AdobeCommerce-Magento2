<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Service class to check if Apple Pay is enabled in the Monei account.
 */
class IsEnabledApplePayInMoneiAccount
{
    /**
     * Google and Apple Payment module configuration.
     */
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;

    /**
     * Service to get available Monei payment methods.
     */
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    /**
     * Constructor for IsEnabledApplePayInMoneiAccount.
     *
     * @param MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig
     *                                                                                          Configuration for Google and Apple Pay
     * @param GetAvailableMoneiPaymentMethods              $getAvailableMoneiPaymentMethods
     *                                                                                          Service to retrieve available payment methods
     */
    public function __construct(
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig,
        GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods
    ) {
        $this->moneiGoogleApplePaymentModuleConfig = $moneiGoogleApplePaymentModuleConfig;
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
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
        $availableMoneiPaymentMethods = $this->getAvailableMoneiPaymentMethods->execute();

        return \in_array(Monei::MONEI_APPLE_CODE, $availableMoneiPaymentMethods, true);
    }
}

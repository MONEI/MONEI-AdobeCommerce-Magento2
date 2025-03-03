<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Service class to check if Google Pay is enabled in the Monei account.
 */
class IsEnabledGooglePayInMoneiAccount
{
    /**
     * Google and Apple Payment module configuration.
     * @var MoneiGoogleApplePaymentModuleConfigInterface
     */
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;

    /**
     * Service to get available Monei payment methods.
     * @var GetAvailableMoneiPaymentMethods
     */
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    /**
     * Constructor for IsEnabledGooglePayInMoneiAccount.
     *
     * @param MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig
     *        Configuration for Google and Apple Pay
     * @param GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods
     *        Service to retrieve available payment methods
     */
    public function __construct(
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig,
        GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods
    ) {
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
        $this->moneiGoogleApplePaymentModuleConfig = $moneiGoogleApplePaymentModuleConfig;
    }

    /**
     * Check if Google Pay is enabled in the Monei account.
     *
     * @return bool True if Google Pay is enabled, false otherwise
     */
    public function execute(): bool
    {
        if (!$this->moneiGoogleApplePaymentModuleConfig->isEnabled()) {
            return false;
        }
        $availableMoneiPaymentMethods = $this->getAvailableMoneiPaymentMethods->execute();

        return \in_array(Monei::MONEI_GOOGLE_CODE, $availableMoneiPaymentMethods, true);
    }
}

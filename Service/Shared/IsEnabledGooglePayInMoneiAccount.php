<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class IsEnabledGooglePayInMoneiAccount
{
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    public function __construct(
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig,
        GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods
    ) {
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
        $this->moneiGoogleApplePaymentModuleConfig = $moneiGoogleApplePaymentModuleConfig;
    }


    public function execute(): bool
    {
        if (!$this->moneiGoogleApplePaymentModuleConfig->isEnabled()) {
            return false;
        }
        $availableMoneiPaymentMethods = $this->getAvailableMoneiPaymentMethods->execute();

        return in_array(Monei::MONEI_GOOGLE_CODE, $availableMoneiPaymentMethods, true);
    }
}

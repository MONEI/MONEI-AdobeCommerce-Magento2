<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class IsEnabledApplePayInMoneiAccount
{
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig;
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    public function __construct(
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentModuleConfig,
        GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods
    ) {
        $this->moneiGoogleApplePaymentModuleConfig = $moneiGoogleApplePaymentModuleConfig;
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
    }

    public function execute(): bool
    {
        if (!$this->moneiGoogleApplePaymentModuleConfig->isEnabled()) {
            return false;
        }
        $availableMoneiPaymentMethods = $this->getAvailableMoneiPaymentMethods->execute();

        return \in_array(Monei::MONEI_APPLE_CODE, $availableMoneiPaymentMethods, true);
    }
}

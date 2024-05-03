<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Model\Payment\Monei;

class IsEnabledApplePayInMoneiAccount
{
    private GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods;

    public function __construct(GetAvailableMoneiPaymentMethods $getAvailableMoneiPaymentMethods)
    {
        $this->getAvailableMoneiPaymentMethods = $getAvailableMoneiPaymentMethods;
    }


    public function execute(): bool
    {
        $availableMoneiPaymentMethods = $this->getAvailableMoneiPaymentMethods->execute();

        return in_array(Monei::MONEI_APPLE_CODE, $availableMoneiPaymentMethods, true);
    }
}

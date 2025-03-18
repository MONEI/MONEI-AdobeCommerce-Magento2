<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Model\Payment\Monei;

class PaymentMethodMap
{
    /**
     * Get Monei payment codes for a given Magento payment code.
     *
     * @param string $magentoPaymentCode Magento payment method code
     *
     * @return array Array of corresponding Monei payment codes
     */
    public function execute(string $magentoPaymentCode): array
    {
        return Monei::PAYMENT_METHOD_MAP[$magentoPaymentCode] ?? [];
    }
}

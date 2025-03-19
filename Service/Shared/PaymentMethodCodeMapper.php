<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Model\Payment\Monei;

class PaymentMethodCodeMapper
{
    /**
     * Get Monei redirect payment codes for a given Magento payment code.
     *
     * @param string $magentoPaymentCode Magento payment method code
     *
     * @return array Array of corresponding Monei redirect payment codes
     */
    public function execute(string $magentoPaymentCode): array
    {
        return Monei::REDIRECT_PAYMENT_MAP[$magentoPaymentCode] ?? [];
    }
}

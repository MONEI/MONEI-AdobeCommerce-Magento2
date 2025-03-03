<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Model\Payment\Monei;

class GetMoneiPaymentCodesByMagentoPaymentCodeRedirect
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
        return Monei::MAPPER_MAGENTO_MONEI_PAYMENT_CODE_REDIRECT[$magentoPaymentCode] ?? [];
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Shared;

use Monei\MoneiPayment\Model\Payment\Monei;

class GetMoneiPaymentCodesByMagentoPaymentCode
{
    public function execute(string $magentoPaymentCode): array
    {
        return Monei::MAPPER_MAGENTO_MONEI_PAYMENT_CODE[$magentoPaymentCode] ?? [];
    }
}

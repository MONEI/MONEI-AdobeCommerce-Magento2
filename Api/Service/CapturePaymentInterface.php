<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Monei\Model\Payment;

/**
 * Monei capture payment REST integration service interface.
 */
interface CapturePaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data Payment data to capture
     * @return Payment MONEI SDK Payment object
     */
    public function execute(array $data): Payment;
}

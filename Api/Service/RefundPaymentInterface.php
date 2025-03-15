<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Monei\Model\Payment;

/**
 * Monei refund payment REST integration service interface.
 */
interface RefundPaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data Refund data including payment_id and amount
     * @return Payment MONEI SDK Payment object
     */
    public function execute(array $data): Payment;
}

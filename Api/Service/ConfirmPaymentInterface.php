<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Monei\Model\Payment;

/**
 * Monei confirm payment REST integration service interface.
 */
interface ConfirmPaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data Payment data to confirm
     * @return Payment MONEI SDK Payment object
     */
    public function execute(array $data): Payment;
}

<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Monei\Model\Payment;

/**
 * Monei cancel payment REST integration service interface.
 */
interface CancelPaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data
     * @return Payment MONEI SDK Payment object
     */
    public function execute(array $data): Payment;
}

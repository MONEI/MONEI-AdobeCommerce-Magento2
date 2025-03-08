<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use OpenAPI\Client\Model\Payment;

/**
 * Monei create payment REST integration service interface.
 */
interface CreatePaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data Payment data to create payment
     * @return Payment MONEI SDK Payment object
     */
    public function execute(array $data): Payment;
}

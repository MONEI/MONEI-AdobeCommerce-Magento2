<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use OpenAPI\Client\Model\Payment;

/**
 * Monei get payment REST integration service interface.
 */
interface GetPaymentInterface
{
    /**
     * Service execute method.
     *
     * @param string $paymentId
     * @return Payment MONEI SDK Payment object
     */
    public function execute(string $paymentId): Payment;
}

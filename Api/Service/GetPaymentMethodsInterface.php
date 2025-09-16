<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

use Monei\Model\PaymentMethods;

/**
 * Monei get payment methods REST integration service interface.
 */
interface GetPaymentMethodsInterface
{
    /**
     * Service execute method to retrieve payment methods.
     *
     * @param string|null $accountId Optional account ID to filter payment methods
     * @return PaymentMethods MONEI SDK payment methods object
     */
    public function execute(?string $accountId = null): PaymentMethods;
}

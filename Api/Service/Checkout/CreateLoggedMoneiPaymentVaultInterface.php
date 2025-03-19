<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Checkout;

interface CreateLoggedMoneiPaymentVaultInterface
{
    /**
     * Create a Monei payment using a saved vault token for a logged-in customer.
     *
     * @param string $cartId
     * @param string $publicHash
     *
     * @return mixed[]
     */
    public function execute(string $cartId, string $publicHash);
}

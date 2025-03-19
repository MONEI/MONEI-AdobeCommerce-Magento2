<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Checkout;

interface CreateGuestMoneiPaymentInSiteInterface
{
    /**
     * Create a Monei payment for a guest customer.
     *
     * @param string $cartId
     * @param string $email
     *
     * @return mixed[]
     */
    public function execute(string $cartId, string $email);
}

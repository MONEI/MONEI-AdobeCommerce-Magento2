<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Checkout;

interface CreateLoggedMoneiPaymentVaultInterface
{
    /**
     * Create a Monei payment using a saved vault token for a logged-in customer
     *
     * @param string $cartId
     * @param string $publicHash
     *
     * @return array
     */
    public function execute(string $cartId, string $publicHash): array;
}

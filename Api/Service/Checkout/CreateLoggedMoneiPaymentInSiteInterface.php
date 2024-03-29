<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Checkout;

interface CreateLoggedMoneiPaymentInSiteInterface
{
    /**
     * @param string $cartId
     * @param string $email
     * @return array
     */
    public function execute(string $cartId, string $email): array;
}

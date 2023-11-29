<?php

/**
 * @author Interactiv4 Team
 * @copyright Copyright © Interactiv4 (https://www.interactiv4.com)
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

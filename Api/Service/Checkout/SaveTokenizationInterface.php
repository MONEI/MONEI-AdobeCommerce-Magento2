<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Checkout;

interface SaveTokenizationInterface
{
    /**
     * Save tokenization preference for the current cart.
     *
     * @param string $cartId
     * @param int $isVaultChecked
     *
     * @return mixed[]
     */
    public function execute(string $cartId, int $isVaultChecked = 0);
}

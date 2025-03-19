<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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

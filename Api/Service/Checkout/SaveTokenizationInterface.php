<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Checkout;

interface SaveTokenizationInterface
{
    /**
     * @param string $cartId
     * @param int $isVaultChecked
     * @return array
     */
    public function execute(string $cartId, int $isVaultChecked = 0): array;
}

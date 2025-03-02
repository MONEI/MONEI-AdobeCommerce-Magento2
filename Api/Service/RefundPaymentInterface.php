<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Monei refund payment REST integration service interface.
 */
interface RefundPaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data
     */
    public function execute(array $data): array;
}

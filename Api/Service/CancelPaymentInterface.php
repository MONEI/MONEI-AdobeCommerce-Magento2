<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Monei cancel payment REST integration service interface.
 */
interface CancelPaymentInterface
{
    /**
     * Service execute method.
     *
     * @param array $data
     */
    public function execute(array $data): array;
}

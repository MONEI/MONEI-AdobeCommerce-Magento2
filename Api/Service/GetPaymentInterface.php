<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Monei get payment REST integration service interface.
 */
interface GetPaymentInterface
{
    /**
     * Service execute method
     *
     * @param string $paymentId
     * @return array
     */
    public function execute(string $paymentId): array;
}

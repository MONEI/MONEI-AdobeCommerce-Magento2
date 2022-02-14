<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Monei create payment REST integration service interface.
 */
interface CreatePaymentInterface
{
    /**
     * Service execute method
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data): array;
}

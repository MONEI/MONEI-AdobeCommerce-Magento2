<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Generate invoice for order service interface.
 */
interface GenerateInvoiceInterface
{
    /**
     * Service execute method.
     */
    public function execute(array $data): void;
}

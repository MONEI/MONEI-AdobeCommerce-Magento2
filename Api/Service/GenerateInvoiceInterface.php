<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Generate invoice for order service interface
 */
interface GenerateInvoiceInterface
{
    /**
     * Service execute method
     *
     * @param array $data
     * @return void
     */
    public function execute(array $data): void;
}

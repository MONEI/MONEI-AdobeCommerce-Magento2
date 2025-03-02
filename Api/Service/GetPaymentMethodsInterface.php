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
interface GetPaymentMethodsInterface
{
    /**
     * Service execute method.
     */
    public function execute(): array;
}

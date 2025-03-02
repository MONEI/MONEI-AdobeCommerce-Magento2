<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service;

/**
 * Set order status and state depending from response data service interface.
 */
interface SetOrderStatusAndStateInterface
{
    /**
     * Service execute method.
     */
    public function execute(array $data): bool;
}

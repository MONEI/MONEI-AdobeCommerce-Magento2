<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Config;

/**
 * Get payment method configuration interface.
 */
interface AllMoneiPaymentModuleConfigInterface
{
    /**
     * Check if any payment methods is enabled.
     *
     * @param null $storeId
     */
    public function isAnyPaymentEnabled($storeId = null): bool;
}

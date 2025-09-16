<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isAnyPaymentEnabled(?int $storeId = null): bool;
}

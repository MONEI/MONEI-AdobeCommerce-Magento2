<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

interface OrderLockManagerInterface
{
    public const ORDER_LOCKED_PREFIX = 'ORDER_LOCKED_';
    public const ORDER_LOCKED_TIMEOUT = 5;

    /**
     * Lock an order for processing.
     *
     * @param string $incrementId Order increment ID
     *
     * @return bool True if lock was acquired, false otherwise
     */
    public function lock(string $incrementId): bool;

    /**
     * Unlock a previously locked order.
     *
     * @param string $incrementId Order increment ID
     *
     * @return bool True if order was unlocked, false otherwise
     */
    public function unlock(string $incrementId): bool;

    /**
     * Check if an order is currently locked.
     *
     * @param string $incrementId Order increment ID
     *
     * @return bool True if order is locked, false otherwise
     */
    public function isLocked(string $incrementId): bool;
}

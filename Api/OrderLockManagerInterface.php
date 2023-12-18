<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

interface OrderLockManagerInterface
{
    public const ORDER_LOCKED_PREFIX = 'ORDER_LOCKED_';
    public const ORDER_LOCKED_TIMEOUT = 5;

    public function lock(string $incrementId): bool;

    public function unlock(string $incrementId): bool;

    public function isLocked(string $incrementId): bool;
}

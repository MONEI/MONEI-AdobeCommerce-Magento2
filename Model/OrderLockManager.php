<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Framework\Lock\LockManagerInterface;
use Monei\MoneiPayment\Api\OrderLockManagerInterface;

class OrderLockManager implements OrderLockManagerInterface
{
    private LockManagerInterface $lockManager;

    public function __construct(
        LockManagerInterface $lockManager
    ) {
        $this->lockManager = $lockManager;
    }

    public function lock(string $incrementId): bool
    {
        return $this->lockManager->lock(
            $this->getLockName($incrementId),
            self::ORDER_LOCKED_TIMEOUT
        );
    }
    public function unlock(string $incrementId): bool
    {
        $unlocked = false;
        $maxIterations = 10;
        while ($this->isLocked($incrementId) && $maxIterations) {
            $unlocked = $this->lockManager->unlock($this->getLockName($incrementId));
            --$maxIterations;
        }

        return $unlocked;
    }

    public function isLocked(string $incrementId): bool
    {
        return $this->lockManager->isLocked($this->getLockName($incrementId));
    }

    private function getLockName(string $incrementId): string
    {
        return self::ORDER_LOCKED_PREFIX . $incrementId;
    }
}

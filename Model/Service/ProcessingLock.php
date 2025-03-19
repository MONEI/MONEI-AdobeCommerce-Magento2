<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service for managing processing locks to prevent race conditions
 */
class ProcessingLock implements ProcessingLockInterface
{
    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     */
    public function __construct(
        LockManagerInterface $lockManager,
        Logger $logger
    ) {
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function executeWithLock(string $orderId, string $paymentId, callable $callback)
    {
        return $this->lockManager->executeWithPaymentLock($orderId, $paymentId, $callback);
    }

    /**
     * @inheritdoc
     */
    public function executeWithOrderLock(string $incrementId, callable $callback)
    {
        return $this->lockManager->executeWithOrderLock($incrementId, $callback);
    }

    /**
     * @inheritdoc
     */
    public function acquireLock(string $orderId, string $paymentId, int $timeout = LockManagerInterface::DEFAULT_LOCK_TIMEOUT): bool
    {
        return $this->lockManager->lockPayment($orderId, $paymentId, $timeout);
    }

    /**
     * @inheritdoc
     */
    public function acquireOrderLock(string $incrementId, int $timeout = LockManagerInterface::DEFAULT_LOCK_TIMEOUT): bool
    {
        return $this->lockManager->lockOrder($incrementId, $timeout);
    }

    /**
     * @inheritdoc
     */
    public function releaseLock(string $orderId, string $paymentId): bool
    {
        return $this->lockManager->unlockPayment($orderId, $paymentId);
    }

    /**
     * @inheritdoc
     */
    public function releaseOrderLock(string $incrementId): bool
    {
        return $this->lockManager->unlockOrder($incrementId);
    }

    /**
     * @inheritdoc
     */
    public function isLocked(string $orderId, string $paymentId): bool
    {
        return $this->lockManager->isPaymentLocked($orderId, $paymentId);
    }

    /**
     * @inheritdoc
     */
    public function isOrderLocked(string $incrementId): bool
    {
        return $this->lockManager->isOrderLocked($incrementId);
    }

    /**
     * @inheritdoc
     */
    public function waitForUnlock(
        string $orderId,
        string $paymentId,
        int $timeout = LockManagerInterface::DEFAULT_MAX_WAIT_TIME,
        int $interval = LockManagerInterface::DEFAULT_WAIT_INTERVAL
    ): bool {
        return $this->lockManager->waitForPaymentUnlock($orderId, $paymentId, $timeout, $interval);
    }
}

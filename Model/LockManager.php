<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface as MagentoLockManagerInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Unified lock manager for MONEI payment processing
 */
class LockManager implements LockManagerInterface
{
    /**
     * @var MagentoLockManagerInterface
     */
    private MagentoLockManagerInterface $lockManager;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param MagentoLockManagerInterface $lockManager
     * @param Logger $logger
     */
    public function __construct(
        MagentoLockManagerInterface $lockManager,
        Logger $logger
    ) {
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function lockOrder(string $incrementId, int $timeout = self::DEFAULT_LOCK_TIMEOUT): bool
    {
        $lockName = $this->getOrderLockName($incrementId);
        $locked = $this->lockManager->lock($lockName, $timeout);

        if ($locked) {
            $this->logger->info(
                '[LockManager] Order lock acquired',
                [
                    'order_id' => $incrementId,
                    'timeout' => $timeout
                ]
            );
        } else {
            $this->logger->warning(
                '[LockManager] Failed to acquire order lock',
                [
                    'order_id' => $incrementId,
                    'timeout' => $timeout
                ]
            );
        }

        return $locked;
    }

    /**
     * @inheritdoc
     */
    public function unlockOrder(string $incrementId): bool
    {
        $lockName = $this->getOrderLockName($incrementId);
        $unlocked = false;
        $maxIterations = 3;

        while ($this->isOrderLocked($incrementId) && $maxIterations > 0) {
            $unlocked = $this->lockManager->unlock($lockName);
            --$maxIterations;
        }

        if ($unlocked) {
            $this->logger->info(
                '[LockManager] Order lock released',
                ['order_id' => $incrementId]
            );
        } else {
            $this->logger->warning(
                '[LockManager] Failed to release order lock',
                ['order_id' => $incrementId]
            );
        }

        return $unlocked;
    }

    /**
     * @inheritdoc
     */
    public function isOrderLocked(string $incrementId): bool
    {
        $lockName = $this->getOrderLockName($incrementId);

        return $this->lockManager->isLocked($lockName);
    }

    /**
     * @inheritdoc
     */
    public function lockPayment(string $orderId, string $paymentId, int $timeout = self::DEFAULT_LOCK_TIMEOUT): bool
    {
        $lockName = $this->getPaymentLockName($orderId, $paymentId);
        $locked = $this->lockManager->lock($lockName, $timeout);

        if ($locked) {
            $this->logger->info(
                '[LockManager] Payment lock acquired',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'timeout' => $timeout
                ]
            );
        } else {
            $this->logger->warning(
                '[LockManager] Failed to acquire payment lock',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'timeout' => $timeout
                ]
            );
        }

        return $locked;
    }

    /**
     * @inheritdoc
     */
    public function unlockPayment(string $orderId, string $paymentId): bool
    {
        $lockName = $this->getPaymentLockName($orderId, $paymentId);
        $unlocked = false;
        $maxIterations = 3;

        while ($this->isPaymentLocked($orderId, $paymentId) && $maxIterations > 0) {
            $unlocked = $this->lockManager->unlock($lockName);
            --$maxIterations;
        }

        if ($unlocked) {
            $this->logger->info(
                '[LockManager] Payment lock released',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]
            );
        } else {
            $this->logger->warning(
                '[LockManager] Failed to release payment lock',
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]
            );
        }

        return $unlocked;
    }

    /**
     * @inheritdoc
     */
    public function isPaymentLocked(string $orderId, string $paymentId): bool
    {
        $lockName = $this->getPaymentLockName($orderId, $paymentId);

        return $this->lockManager->isLocked($lockName);
    }

    /**
     * @inheritdoc
     */
    public function waitForPaymentUnlock(
        string $orderId,
        string $paymentId,
        int $timeout = self::DEFAULT_MAX_WAIT_TIME,
        int $interval = self::DEFAULT_WAIT_INTERVAL
    ): bool {
        $startTime = microtime(true);
        $endTime = $startTime + $timeout;

        $this->logger->info(
            '[LockManager] Waiting for payment lock release',
            [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'timeout' => $timeout,
                'interval' => $interval
            ]
        );

        while (microtime(true) < $endTime) {
            if (!$this->isPaymentLocked($orderId, $paymentId)) {
                $waited = number_format(microtime(true) - $startTime, 2);
                $this->logger->info(
                    '[LockManager] Payment lock released',
                    [
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'waited' => $waited
                    ]
                );

                return true;
            }

            // Sleep for the specified interval
            usleep($interval * 1000);
        }

        $this->logger->warning(
            '[LockManager] Timeout waiting for payment lock release',
            [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'timeout' => $timeout
            ]
        );

        return false;
    }

    /**
     * @inheritdoc
     */
    public function executeWithPaymentLock(
        string $orderId,
        string $paymentId,
        callable $callback,
        int $timeout = self::DEFAULT_LOCK_TIMEOUT
    ) {
        $locked = $this->lockPayment($orderId, $paymentId, $timeout);

        if (!$locked) {
            throw new LocalizedException(__(
                'Unable to acquire payment lock for order %1, payment %2',
                $orderId,
                $paymentId
            ));
        }

        try {
            return $callback();
        } finally {
            $this->unlockPayment($orderId, $paymentId);
        }
    }

    /**
     * @inheritdoc
     */
    public function executeWithOrderLock(
        string $incrementId,
        callable $callback,
        int $timeout = self::DEFAULT_LOCK_TIMEOUT
    ) {
        $locked = $this->lockOrder($incrementId, $timeout);

        if (!$locked) {
            throw new LocalizedException(__(
                'Unable to acquire order lock for order %1',
                $incrementId
            ));
        }

        try {
            return $callback();
        } finally {
            $this->unlockOrder($incrementId);
        }
    }

    /**
     * Get the lock name for an order
     *
     * @param string $incrementId
     * @return string
     */
    private function getOrderLockName(string $incrementId): string
    {
        return self::ORDER_LOCK_PREFIX . $incrementId;
    }

    /**
     * Get the lock name for a payment
     *
     * @param string $orderId
     * @param string $paymentId
     * @return string
     */
    private function getPaymentLockName(string $orderId, string $paymentId): string
    {
        return self::PAYMENT_LOCK_PREFIX . $orderId . '_' . $paymentId;
    }
}

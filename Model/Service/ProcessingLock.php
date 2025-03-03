<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Magento\Framework\Lock\LockManagerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service for managing processing locks to prevent race conditions.
 */
class ProcessingLock
{
    // Lock prefix for Monei payment processing
    private const LOCK_PREFIX = 'monei_payment_processing_';

    // Lock timeout in seconds (5 minutes)
    private const LOCK_TIMEOUT = 300;

    /** @var LockManagerInterface */
    private $lockManager;

    /** @var Logger */
    private $logger;

    /**
     * Constructor.
     *
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
     * Execute a callback function with a lock to prevent race conditions.
     *
     * This method ensures the lock is always released, even if an exception occurs.
     *
     * @param string   $orderId   Order increment ID
     * @param string   $paymentId Monei payment ID
     * @param callable $callback  Function to execute while holding the lock
     *
     * @return mixed The result of the callback function, or false if lock couldn't be acquired
     */
    public function executeWithLock(string $orderId, string $paymentId, callable $callback)
    {
        $lockName = $this->getLockName($orderId, $paymentId);

        try {
            $locked = $this->lockManager->lock($lockName, self::LOCK_TIMEOUT);

            if (!$locked) {
                $this->logger->info(\sprintf(
                    '[Payment already processing] Order %s, payment %s',
                    $orderId,
                    $paymentId
                ));

                return false;
            }

            try {
                // Execute the callback while holding the lock
                return $callback();
            } finally {
                // Always release the lock, even if an exception occurred in the callback
                $this->releaseLock($orderId, $paymentId);
            }
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                '[Error in lock management] Order %s: %s',
                $orderId,
                $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Acquire a lock for processing an order payment.
     *
     * @param string $orderId   Order increment ID
     * @param string $paymentId Monei payment ID
     *
     * @return bool True if lock acquired, false otherwise
     */
    public function acquireLock(string $orderId, string $paymentId): bool
    {
        $lockName = $this->getLockName($orderId, $paymentId);
        $locked = $this->lockManager->lock($lockName, self::LOCK_TIMEOUT);

        if ($locked) {
            $this->logger->info(\sprintf(
                '[Lock acquired] Order %s, payment %s',
                $orderId,
                $paymentId
            ));
        } else {
            $this->logger->error(\sprintf(
                '[Failed to acquire lock] Order %s, payment %s, timeout: %d seconds',
                $orderId,
                $paymentId,
                self::LOCK_TIMEOUT
            ));
        }

        return $locked;
    }

    /**
     * Release a lock for processing an order payment.
     *
     * @param string $orderId   Order increment ID
     * @param string $paymentId Monei payment ID
     *
     * @return bool True if lock released, false otherwise
     */
    public function releaseLock(string $orderId, string $paymentId): bool
    {
        $lockName = $this->getLockName($orderId, $paymentId);
        $released = $this->lockManager->unlock($lockName);

        if ($released) {
            $this->logger->info(\sprintf(
                '[Lock released] Order %s, payment %s',
                $orderId,
                $paymentId
            ));
        } else {
            $this->logger->error(\sprintf(
                '[Failed to release lock] Order %s, payment %s',
                $orderId,
                $paymentId
            ));
        }

        return $released;
    }

    /**
     * Generate a unique lock name for a given order and payment ID.
     *
     * @param string $orderId   Order increment ID
     * @param string $paymentId Monei payment ID
     */
    private function getLockName(string $orderId, string $paymentId): string
    {
        return self::LOCK_PREFIX . $orderId . '_' . $paymentId;
    }
}

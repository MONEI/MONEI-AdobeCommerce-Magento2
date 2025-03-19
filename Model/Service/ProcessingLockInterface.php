<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Magento\Framework\Exception\LocalizedException;

/**
 * Interface for managing processing locks to prevent race conditions
 */
interface ProcessingLockInterface
{
    /**
     * Execute a callback function with a payment lock to prevent race conditions
     *
     * This method ensures the lock is always released, even if an exception occurs
     *
     * @param string $orderId Order ID
     * @param string $paymentId MONEI payment ID
     * @param callable $callback Function to execute while holding the lock
     * @return mixed The result of the callback function
     * @throws LocalizedException If the lock cannot be acquired
     */
    public function executeWithLock(string $orderId, string $paymentId, callable $callback);

    /**
     * Execute a callback function with an order lock to prevent race conditions
     *
     * This method ensures the lock is always released, even if an exception occurs
     *
     * @param string $incrementId Order increment ID
     * @param callable $callback Function to execute while holding the lock
     * @return mixed The result of the callback function
     * @throws LocalizedException If the lock cannot be acquired
     */
    public function executeWithOrderLock(string $incrementId, callable $callback);

    /**
     * Acquire a lock for processing a payment
     *
     * @param string $orderId Order ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Lock timeout in seconds
     * @return bool True if lock acquired, false otherwise
     */
    public function acquireLock(string $orderId, string $paymentId, int $timeout = 300): bool;

    /**
     * Acquire a lock for processing an order
     *
     * @param string $incrementId Order increment ID
     * @param int $timeout Lock timeout in seconds
     * @return bool True if lock acquired, false otherwise
     */
    public function acquireOrderLock(string $incrementId, int $timeout = 300): bool;

    /**
     * Release a lock for a payment
     *
     * @param string $orderId Order ID
     * @param string $paymentId MONEI payment ID
     * @return bool True if lock released, false otherwise
     */
    public function releaseLock(string $orderId, string $paymentId): bool;

    /**
     * Release a lock for an order
     *
     * @param string $incrementId Order increment ID
     * @return bool True if lock released, false otherwise
     */
    public function releaseOrderLock(string $incrementId): bool;

    /**
     * Check if a payment is locked
     *
     * @param string $orderId Order ID
     * @param string $paymentId MONEI payment ID
     * @return bool True if locked, false otherwise
     */
    public function isLocked(string $orderId, string $paymentId): bool;

    /**
     * Check if an order is locked
     *
     * @param string $incrementId Order increment ID
     * @return bool True if locked, false otherwise
     */
    public function isOrderLocked(string $incrementId): bool;

    /**
     * Wait for a payment lock to be released
     *
     * @param string $orderId Order ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Maximum time to wait in seconds
     * @param int $interval Wait interval in milliseconds
     * @return bool True if lock was released within timeout, false otherwise
     */
    public function waitForUnlock(
        string $orderId,
        string $paymentId,
        int $timeout = 30,
        int $interval = 100
    ): bool;
}

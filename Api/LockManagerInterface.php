<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

/**
 * Interface for the unified locking mechanism
 */
interface LockManagerInterface
{
    /**
     * Order lock prefix
     */
    public const ORDER_LOCK_PREFIX = 'MONEI_ORDER_LOCK_';

    /**
     * Payment lock prefix
     */
    public const PAYMENT_LOCK_PREFIX = 'MONEI_PAYMENT_LOCK_';

    /**
     * Default lock timeout in seconds (5 seconds)
     */
    public const DEFAULT_LOCK_TIMEOUT = 5;

    /**
     * Maximum lock timeout in seconds (30 minutes)
     */
    public const MAX_LOCK_TIMEOUT = 1800;

    /**
     * Default wait interval in milliseconds (500ms)
     */
    public const DEFAULT_WAIT_INTERVAL = 500;

    /**
     * Default maximum wait time in seconds (15 seconds)
     */
    public const DEFAULT_MAX_WAIT_TIME = 15;

    /**
     * Lock an order for processing
     *
     * @param string $incrementId Order increment ID
     * @param int $timeout Lock acquisition timeout in seconds
     * @return bool True if lock acquired, false otherwise
     */
    public function lockOrder(string $incrementId, int $timeout = self::DEFAULT_LOCK_TIMEOUT): bool;

    /**
     * Unlock an order
     *
     * @param string $incrementId Order increment ID
     * @return bool True if lock released, false otherwise
     */
    public function unlockOrder(string $incrementId): bool;

    /**
     * Check if an order is locked
     *
     * @param string $incrementId Order increment ID
     * @return bool True if locked, false otherwise
     */
    public function isOrderLocked(string $incrementId): bool;

    /**
     * Lock a specific payment for processing
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Lock acquisition timeout in seconds
     * @return bool True if lock acquired, false otherwise
     */
    public function lockPayment(string $orderId, string $paymentId, int $timeout = self::DEFAULT_LOCK_TIMEOUT): bool;

    /**
     * Unlock a payment
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @return bool True if lock released, false otherwise
     */
    public function unlockPayment(string $orderId, string $paymentId): bool;

    /**
     * Check if a payment is locked
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @return bool True if locked, false otherwise
     */
    public function isPaymentLocked(string $orderId, string $paymentId): bool;

    /**
     * Wait for a payment to be unlocked
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Maximum wait time in seconds
     * @param int $interval Check interval in milliseconds
     * @return bool True if unlocked before timeout, false otherwise
     */
    public function waitForPaymentUnlock(
        string $orderId,
        string $paymentId,
        int $timeout = self::DEFAULT_MAX_WAIT_TIME,
        int $interval = self::DEFAULT_WAIT_INTERVAL
    ): bool;

    /**
     * Execute a callback with a payment lock
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param callable $callback Function to execute with the lock
     * @param int $timeout Lock acquisition timeout in seconds
     * @return mixed The result of the callback
     * @throws \Exception If lock cannot be acquired or callback throws exception
     */
    public function executeWithPaymentLock(
        string $orderId,
        string $paymentId,
        callable $callback,
        int $timeout = self::DEFAULT_LOCK_TIMEOUT
    );

    /**
     * Execute a callback with an order lock
     *
     * @param string $incrementId Order increment ID
     * @param callable $callback Function to execute with the lock
     * @param int $timeout Lock acquisition timeout in seconds
     * @return mixed The result of the callback
     * @throws \Exception If lock cannot be acquired or callback throws exception
     */
    public function executeWithOrderLock(
        string $incrementId,
        callable $callback,
        int $timeout = self::DEFAULT_LOCK_TIMEOUT
    );
}

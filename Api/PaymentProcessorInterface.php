<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

/**
 * Interface for processing MONEI payments with concurrency control
 */
interface PaymentProcessorInterface
{
    /**
     * Process payment with locking to prevent race conditions
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param array $paymentData Payment data from MONEI
     * @return PaymentProcessingResultInterface
     */
    public function process(string $orderId, string $paymentId, array $paymentData): PaymentProcessingResultInterface;

    /**
     * Check if a payment is currently being processed
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @return bool
     */
    public function isProcessing(string $orderId, string $paymentId): bool;

    /**
     * Wait for payment processing to complete
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId MONEI payment ID
     * @param int $timeout Maximum time to wait in seconds
     * @return bool True if processing completed, false if timed out
     */
    public function waitForProcessing(string $orderId, string $paymentId, int $timeout = 15): bool;

    /**
     * Get the current payment data from MONEI API
     *
     * @param string $paymentId MONEI payment ID
     * @return array Payment data
     * @throws \Exception When API call fails, with original error message
     */
    public function getPayment(string $paymentId): array;
}

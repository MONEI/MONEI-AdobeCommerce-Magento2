<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

/**
 * Interface for payment processing result
 */
interface PaymentProcessingResultInterface
{
    /**
     * Get the payment status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Get the order ID
     *
     * @return string
     */
    public function getOrderId(): string;

    /**
     * Get the payment ID
     *
     * @return string
     */
    public function getPaymentId(): string;

    /**
     * Check if processing was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * Get any error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;

    /**
     * Get MONEI status code
     *
     * @return string|null
     */
    public function getStatusCode(): ?string;

    /**
     * Get the full error response from MONEI API
     * This allows merchants to see the complete error details
     *
     * @return array|null
     */
    public function getFullErrorResponse(): ?array;

    /**
     * Get a user-friendly error message suitable for display
     *
     * @return string|null
     */
    public function getDisplayErrorMessage(): ?string;

    /**
     * Alternative method for checking success (alias for isSuccessful)
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * Get a generic message about the processing result
     *
     * @return string
     */
    public function getMessage(): string;
}

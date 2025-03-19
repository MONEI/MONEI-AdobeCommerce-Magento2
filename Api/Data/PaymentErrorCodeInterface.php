<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Data;

/**
 * Interface for Monei payment error codes
 */
interface PaymentErrorCodeInterface
{
    /**
     * Error code for when order is not found
     */
    public const ERROR_NOT_FOUND = 'not_found';

    /**
     * Error code for when payment processing failed
     */
    public const ERROR_PROCESSING_FAILED = 'processing_failed';

    /**
     * Error code for when an exception occurred
     */
    public const ERROR_EXCEPTION = 'exception';

    /**
     * Error code for unknown errors
     */
    public const ERROR_UNKNOWN = 'unknown_error';
}

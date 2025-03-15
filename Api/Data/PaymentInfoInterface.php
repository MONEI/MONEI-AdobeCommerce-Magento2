<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Data;

/**
 * Interface for Monei payment additional information
 */
interface PaymentInfoInterface
{
    /**
     * Payment ID in Monei
     */
    public const PAYMENT_ID = 'monei_payment_id';

    /**
     * Payment status in Monei
     */
    public const PAYMENT_STATUS = 'monei_payment_status';

    /**
     * Payment amount in Monei
     */
    public const PAYMENT_AMOUNT = 'monei_payment_amount';

    /**
     * Payment currency in Monei
     */
    public const PAYMENT_CURRENCY = 'monei_payment_currency';

    /**
     * Last update timestamp in Monei
     */
    public const PAYMENT_UPDATED_AT = 'monei_payment_updated_at';

    /**
     * Flag indicating if the payment is authorized
     */
    public const PAYMENT_IS_AUTHORIZED = 'monei_is_authorized';

    /**
     * Flag indicating if the payment is captured
     */
    public const PAYMENT_IS_CAPTURED = 'monei_is_captured';

    /**
     * Capture ID in Monei
     */
    public const CAPTURE_ID = 'monei_capture_id';

    /**
     * Flag indicating if the payment is voided
     */
    public const PAYMENT_IS_VOIDED = 'monei_is_voided';

    /**
     * Constant for payment error code
     */
    public const PAYMENT_ERROR_CODE = 'monei_error_code';

    /**
     * Constant for payment error message
     */
    public const PAYMENT_ERROR_MESSAGE = 'monei_error_message';
}

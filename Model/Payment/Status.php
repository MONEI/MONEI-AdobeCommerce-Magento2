<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Payment;

use OpenAPI\Client\Model\PaymentStatus;

/**
 * Payment status constants from MONEI SDK
 */
class Status
{
    /**
     * Payment is pending processing
     */
    public const PENDING = PaymentStatus::PENDING;

    /**
     * Payment has been authorized but not captured yet
     */
    public const AUTHORIZED = PaymentStatus::AUTHORIZED;

    /**
     * Payment has expired
     */
    public const EXPIRED = PaymentStatus::EXPIRED;

    /**
     * Payment has been canceled
     */
    public const CANCELED = PaymentStatus::CANCELED;

    /**
     * Payment has failed
     */
    public const FAILED = PaymentStatus::FAILED;

    /**
     * Payment has succeeded (captured)
     */
    public const SUCCEEDED = PaymentStatus::SUCCEEDED;

    /**
     * Payment has been partially refunded
     */
    public const PARTIALLY_REFUNDED = PaymentStatus::PARTIALLY_REFUNDED;

    /**
     * Payment has been fully refunded
     */
    public const REFUNDED = PaymentStatus::REFUNDED;

    /**
     * Map of Magento order statuses for MONEI payment statuses
     */
    public const MAGENTO_STATUS_MAP = [
        self::PENDING => 'monei_pending',
        self::AUTHORIZED => 'monei_authorized',
        self::EXPIRED => 'monei_expired',
        self::CANCELED => 'monei_canceled',
        self::FAILED => 'monei_failed',
        self::SUCCEEDED => 'monei_succeeded',
        self::PARTIALLY_REFUNDED => 'monei_partially_refunded',
        self::REFUNDED => 'monei_refunded',
    ];

    /**
     * Get Magento order status for MONEI payment status
     *
     * @param string $moneiStatus
     * @return string|null
     */
    public static function getMagentoStatus(string $moneiStatus): ?string
    {
        return self::MAGENTO_STATUS_MAP[$moneiStatus] ?? null;
    }

    /**
     * Check if payment status is final (no further changes expected)
     *
     * @param string $status
     * @return bool
     */
    public static function isFinalStatus(string $status): bool
    {
        return in_array($status, [
            self::SUCCEEDED,
            self::FAILED,
            self::CANCELED,
            self::EXPIRED,
            self::REFUNDED
        ], true);
    }

    /**
     * Check if payment status is successful
     *
     * @param string $status
     * @return bool
     */
    public static function isSuccessfulStatus(string $status): bool
    {
        return in_array($status, [
            self::SUCCEEDED,
            self::PARTIALLY_REFUNDED
        ], true);
    }

    /**
     * Get all available payment statuses
     *
     * @return array
     */
    public static function getAllStatuses(): array
    {
        return PaymentStatus::getAllowableEnumValues();
    }
}

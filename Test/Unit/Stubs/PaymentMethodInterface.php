<?php

/**
 * Stub for Magento\Checkout\Api\Data\PaymentMethodInterface
 *
 * This is a test stub to satisfy PHPStan analysis.
 * It's not meant to be a complete implementation.
 */

declare(strict_types=1);

namespace Magento\Checkout\Api\Data;

/**
 * Payment method interface
 */
interface PaymentMethodInterface
{
    /**
     * Get payment method code
     *
     * @return string
     */
    public function getCode();

    /**
     * Get payment method title
     *
     * @return string
     */
    public function getTitle();

    /**
     * Get checkout redirect URL
     *
     * @return string|null
     */
    public function getRedirectUrl();
}

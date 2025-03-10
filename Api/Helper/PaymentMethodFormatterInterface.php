<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Helper;

/**
 * Interface for payment method formatting helper
 */
interface PaymentMethodFormatterInterface
{
    /**
     * Format payment method display based on payment information
     *
     * @param array $paymentInfo Payment information array
     * @return string Formatted payment method display text
     */
    public function formatPaymentMethodDisplay(array $paymentInfo): string;

    /**
     * Format wallet information from tokenization method
     *
     * @param string $walletValue Tokenization method value
     * @return string Formatted wallet display text
     */
    public function formatWalletDisplay(string $walletValue): string;

    /**
     * Format phone number with proper spacing and regional formatting
     *
     * @param string $phoneNumber Raw phone number string
     * @return string Formatted phone number
     */
    public function formatPhoneNumber(string $phoneNumber): string;
}

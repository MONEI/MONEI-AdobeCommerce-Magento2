<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Helper;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Api\Helper\PaymentMethodFormatterInterface;

/**
 * Helper class for formatting payment method information
 */
class PaymentMethodFormatter implements PaymentMethodFormatterInterface
{
    /**
     * Format payment method display based on payment information
     *
     * @param array $paymentInfo Payment information array
     * @return string Formatted payment method display text
     */
    public function formatPaymentMethodDisplay(array $paymentInfo): string
    {
        $paymentMethodDisplay = '';
        $methodType = $paymentInfo['method'] ?? '';

        if (isset($paymentInfo['brand']) && !empty($paymentInfo['brand'])) {
            // Card payment display
            $brand = strtolower($paymentInfo['brand']);
            $paymentMethodDisplay = ucfirst($paymentInfo['brand']);

            // Add card type inline if available
            if (isset($paymentInfo['type']) && !empty($paymentInfo['type'])) {
                $paymentMethodDisplay .= ' ' . ucfirst($paymentInfo['type']);
            }

            if (isset($paymentInfo['last4']) && !empty($paymentInfo['last4'])) {
                $paymentMethodDisplay .= ' •••• ' . $paymentInfo['last4'];
            }
        } elseif (!empty($methodType)) {
            // For non-card methods, format the method name nicely
            switch ($methodType) {
                case PaymentMethods::PAYMENT_METHODS_BIZUM:
                    $paymentMethodDisplay = 'Bizum';
                    // Add phone number for Bizum if available
                    if (isset($paymentInfo['phoneNumber']) && !empty($paymentInfo['phoneNumber'])) {
                        // Get last 3 digits of phone number
                        $phoneLength = strlen($paymentInfo['phoneNumber']);
                        $last3 = substr($paymentInfo['phoneNumber'], -3);
                        $paymentMethodDisplay .= ' •••••' . $last3;
                    }
                    break;
                case PaymentMethods::PAYMENT_METHODS_PAYPAL:
                    $paymentMethodDisplay = 'PayPal';
                    break;
                case PaymentMethods::PAYMENT_METHODS_MBWAY:
                    $paymentMethodDisplay = 'MB WAY';
                    break;
                case PaymentMethods::PAYMENT_METHODS_MULTIBANCO:
                    $paymentMethodDisplay = 'Multibanco';
                    break;
                default:
                    // Fallback: Convert camelCase to Title Case with spaces
                    $paymentMethodDisplay = ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $methodType));
            }
        }

        return $paymentMethodDisplay;
    }

    /**
     * Format wallet information from tokenization method
     *
     * @param string $walletValue Tokenization method value
     * @return string Formatted wallet display text
     */
    public function formatWalletDisplay(string $walletValue): string
    {
        if ($walletValue === PaymentMethods::PAYMENT_METHODS_GOOGLE_PAY) {
            return 'Google Pay';
        } elseif ($walletValue === PaymentMethods::PAYMENT_METHODS_APPLE_PAY) {
            return 'Apple Pay';
        } elseif ($walletValue === PaymentMethods::PAYMENT_METHODS_CLICK_TO_PAY) {
            return 'Click to Pay';
        } else {
            // Handle any other values by adding spaces before capital letters and capitalizing first letter
            return ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $walletValue));
        }
    }

    /**
     * Format phone number with proper spacing and regional formatting
     *
     * @param string $phoneNumber Raw phone number string
     * @return string Formatted phone number
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Clean the phone number by removing any non-digit characters
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // If it starts with a plus sign (international format)
        if (strpos($phoneNumber, '+') === 0) {
            // International format for many countries: +XX XXX XXX XXX
            // This is a simplified approach, different countries have different formats
            $length = strlen($phoneNumber);

            if ($length >= 12) { // Full international number
                return substr($phoneNumber, 0, 3) . ' ' .
                       substr($phoneNumber, 3, 3) . ' ' .
                       substr($phoneNumber, 6, 3) . ' ' .
                       substr($phoneNumber, 9);
            } else if ($length >= 9) { // Shorter international number
                return substr($phoneNumber, 0, 3) . ' ' .
                       substr($phoneNumber, 3, 3) . ' ' .
                       substr($phoneNumber, 6);
            }
        } else {
            // National format (no plus sign): XXX XXX XXX
            if (strlen($phoneNumber) >= 9) {
                return substr($phoneNumber, 0, 3) . ' ' .
                       substr($phoneNumber, 3, 3) . ' ' .
                       substr($phoneNumber, 6);
            }
        }

        // If we can't format it properly, return the original cleaned number
        return $phoneNumber;
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Helper;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Api\Helper\PaymentMethodFormatterInterface;
use \Monei\MoneiPayment\Helper\PaymentMethod;

/**
 * Helper class for formatting payment method information
 */
class PaymentMethodFormatter implements PaymentMethodFormatterInterface
{
    /**
     * @var PaymentMethod
     */
    private $paymentMethodHelper;

    /**
     * PaymentMethodFormatter constructor.
     *
     * @param \Monei\MoneiPayment\Helper\PaymentMethod $paymentMethodHelper
     */
    public function __construct(
        PaymentMethod $paymentMethodHelper
    ) {
        $this->paymentMethodHelper = $paymentMethodHelper;
    }

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
            $paymentMethodDisplay = $this->paymentMethodHelper->getPaymentMethodName($brand);

            // Add card type inline if available
            if (isset($paymentInfo['type']) && !empty($paymentInfo['type'])) {
                $paymentMethodDisplay .= ' ' . ucfirst($paymentInfo['type']);
            }

            if (isset($paymentInfo['last4']) && !empty($paymentInfo['last4'])) {
                $paymentMethodDisplay .= ' •••• ' . $paymentInfo['last4'];
            }
        } elseif (!empty($methodType)) {
            // Get payment method display name using the helper
            $methodDetails = $this->paymentMethodHelper->getPaymentMethodByMoneiCode($methodType);

            if ($methodDetails && isset($methodDetails['name'])) {
                $paymentMethodDisplay = $methodDetails['name'];

                // Handle specific methods with extra formatting
                switch ($methodType) {
                    case PaymentMethods::PAYMENT_METHODS_BIZUM:
                        // Add phone number for Bizum if available
                        if (isset($paymentInfo['phoneNumber']) && !empty($paymentInfo['phoneNumber'])) {
                            // Get last 3 digits of phone number
                            $last3 = substr($paymentInfo['phoneNumber'], -3);
                            $paymentMethodDisplay .= ' •••••' . $last3;
                        }

                        break;
                }
            } else {
                // Handle specific methods not in the mapping
                switch ($methodType) {
                    case PaymentMethods::PAYMENT_METHODS_PAYPAL:
                        $paymentMethodDisplay = 'PayPal';

                        // Add additional PayPal information if available
                        $paypalInfo = [];

                        if (isset($paymentInfo['orderId']) && !empty($paymentInfo['orderId'])) {
                            $paypalInfo[] = 'PayPal Order ID: ' . $paymentInfo['orderId'];
                        }

                        if (isset($paymentInfo['payerId']) && !empty($paymentInfo['payerId'])) {
                            $paypalInfo[] = 'PayPal Customer ID: ' . $paymentInfo['payerId'];
                        }

                        if (isset($paymentInfo['email']) && !empty($paymentInfo['email'])) {
                            $paypalInfo[] = 'Customer Email: ' . $paymentInfo['email'];
                        }

                        if (isset($paymentInfo['name']) && !empty($paymentInfo['name'])) {
                            $paypalInfo[] = 'Customer Name: ' . $paymentInfo['name'];
                        }

                        if (!empty($paypalInfo)) {
                            $paymentMethodDisplay .= ' (' . implode(', ', $paypalInfo) . ')';
                        }

                        break;
                    default:
                        // Fallback: Convert camelCase to Title Case with spaces
                        $paymentMethodDisplay = ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $methodType));
                }
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
        $methodDetails = $this->paymentMethodHelper->getPaymentMethodByMoneiCode($walletValue);

        if ($methodDetails && isset($methodDetails['name'])) {
            return $methodDetails['name'];
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

            if ($length >= 12) {  // Full international number
                return substr($phoneNumber, 0, 3) . ' '
                    . substr($phoneNumber, 3, 3) . ' '
                    . substr($phoneNumber, 6, 3) . ' '
                    . substr($phoneNumber, 9);
            } elseif ($length >= 9) {  // Shorter international number
                return substr($phoneNumber, 0, 3) . ' '
                    . substr($phoneNumber, 3, 3) . ' '
                    . substr($phoneNumber, 6);
            }
        } else {
            // National format (no plus sign): XXX XXX XXX
            if (strlen($phoneNumber) >= 9) {
                return substr($phoneNumber, 0, 3) . ' '
                    . substr($phoneNumber, 3, 3) . ' '
                    . substr($phoneNumber, 6);
            }
        }

        // If we can't format it properly, return the original cleaned number
        return $phoneNumber;
    }

    /**
     * Get payment method icon URL based on payment information
     *
     * @param array $paymentInfo Payment information array
     * @return string|null Icon URL
     */
    public function getPaymentMethodIcon(array $paymentInfo): ?string
    {
        $methodType = $paymentInfo['method'] ?? '';
        $cardType = strtolower($paymentInfo['brand'] ?? '');

        if (!empty($methodType)) {
            return $this->paymentMethodHelper->getIconFromPaymentType($methodType, $cardType);
        } elseif (!empty($cardType)) {
            return $this->paymentMethodHelper->getCardIcon($cardType);
        }

        return null;
    }

    /**
     * Get payment method dimensions based on payment information
     *
     * @param array $paymentInfo Payment information array
     * @return array Width and height values
     */
    public function getPaymentMethodDimensions(array $paymentInfo): array
    {
        $methodType = $paymentInfo['method'] ?? '';
        $cardType = strtolower($paymentInfo['brand'] ?? '');

        if (!empty($methodType)) {
            $methodDetails = $this->paymentMethodHelper->getPaymentMethodByMoneiCode($methodType);
            if ($methodDetails) {
                return [
                    'width' => $methodDetails['width'] ?? '40px',
                    'height' => $methodDetails['height'] ?? '24px'
                ];
            }
        } elseif (!empty($cardType)) {
            return $this->paymentMethodHelper->getPaymentMethodDimensions($cardType);
        }

        return [
            'width' => '40px',
            'height' => '24px'
        ];
    }

    /**
     * Generate HTML for payment method icon
     *
     * @param array $paymentInfo Payment information array
     * @param array $attributes Additional HTML attributes for the img tag
     * @return string HTML img tag
     */
    public function getPaymentMethodIconHtml(array $paymentInfo, array $attributes = []): string
    {
        $iconUrl = $this->getPaymentMethodIcon($paymentInfo);
        if (!$iconUrl) {
            return '';
        }

        $dimensions = $this->getPaymentMethodDimensions($paymentInfo);
        $alt = $this->formatPaymentMethodDisplay($paymentInfo);

        $htmlAttributes = [
            'src' => $iconUrl,
            'alt' => $alt,
            'title' => $alt,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'class' => 'payment-icon'
        ];

        // Merge with additional attributes
        $htmlAttributes = array_merge($htmlAttributes, $attributes);

        // Build HTML attributes string
        $attributesString = '';
        foreach ($htmlAttributes as $key => $value) {
            $attributesString .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }

        return '<img' . $attributesString . '>';
    }
}

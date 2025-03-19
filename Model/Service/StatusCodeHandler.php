<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Magento\Framework\Phrase;

/**
 * Service for handling MONEI status codes and messages
 */
class StatusCodeHandler
{
    /**
     * MONEI status codes and their descriptions
     */
    private const STATUS_CODES = [
        'E000' => 'Transaction approved',
        'E999' => 'Service internal error. Please contact support',
        'E101' => 'Error with payment processor configuration. Check this in your dashboard or contact MONEI for support',
        'E102' => 'Invalid or inactive MID. Please contact the acquiring entity',
        'E103' => 'Operation not allowed/configured for this merchant. Please contact the acquiring entity or MONEI for support',
        'E104' => 'Partial captures are not enabled in your account, please contact MONEI support',
        'E105' => 'MOTO Payment are not enabled in your account, please contact MONEI support',
        'E150' => 'Invalid or malformed request. Please check the message format',
        'E151' => 'Missing or malformed signature/auth',
        'E152' => 'Error while decrypting request',
        'E153' => 'Pre-authorization is expired and cannot be canceled or captured',
        'E154' => 'The payment date cannot be less than the cancellation or capture date',
        'E155' => 'The cancellation date exceeded the date allowed for pre-authorized operations',
        'E200' => 'Transaction failed during payment processing',
        'E201' => 'Transaction declined by the card-issuing bank',
        'E202' => 'Transaction declined by the issuing bank',
        'E203' => 'Payment method not allowed',
        'E204' => 'Wrong or not allowed currency',
        'E205' => 'Incorrect reference / transaction does not exist',
        'E207' => 'Transaction failed: process time exceeded',
        'E208' => 'Transaction is currently being processed',
        'E209' => 'Duplicated operation',
        'E210' => 'Wrong or not allowed payment amount',
        'E211' => 'Refund declined by processor',
        'E212' => 'Transaction has already been captured',
        'E213' => 'Transaction has already been canceled',
        'E214' => 'The amount to be captured cannot exceed the pre-authorized amount',
        'E215' => 'The transaction to be captured has not been pre-authorized yet',
        'E216' => 'The transaction to be canceled has not been pre-authorized yet',
        'E217' => 'Transaction denied by processor to avoid duplicated operations',
        'E218' => 'Error during payment request validation',
        'E219' => 'Refund declined due to exceeded amount',
        'E220' => 'Transaction has already been fully refunded',
        'E221' => 'Transaction declined due to insufficient funds',
        'E222' => 'The user has canceled the payment',
        'E223' => 'Waiting for the transaction to be completed',
        'E224' => 'No reason to decline',
        'E225' => 'Refund not allowed',
        'E226' => 'Transaction cannot be completed, violation of law',
        'E227' => 'Stop Payment Order',
        'E228' => 'Strong Customer Authentication required',
        'E300' => 'Transaction declined due to security restrictions',
        'E301' => '3D Secure authentication failed',
        'E302' => 'Authentication process timed out. Please try again',
        'E303' => 'An error occurred during the 3D Secure process',
        'E304' => 'Invalid or malformed 3D Secure request',
        'E305' => 'Exemption not allowed',
        'E306' => 'Exemption error',
        'E307' => 'Fraud control error',
        'E308' => 'External MPI received wrong. Please check the data',
        'E309' => 'External MPI not enabled. Please contact support',
        'E310' => 'Transaction confirmation rejected by the merchant',
        'E500' => 'Transaction declined during card payment process',
        'E501' => 'Card rejected: invalid card number',
        'E502' => 'Card rejected: wrong expiration date',
        'E503' => 'Card rejected: wrong CVC/CVV2 number',
        'E504' => 'Card number not registered',
        'E505' => 'Card is expired',
        'E506' => 'Error during payment authorization. Please try again',
        'E507' => 'Cardholder has canceled the payment',
        'E508' => 'Transaction declined: AMEX cards not accepted by payment processor',
        'E509' => 'Card blocked temporarily or under suspicion of fraud',
        'E510' => 'Card does not allow pre-authorization operations',
        'E511' => 'CVC/CVV2 number is required',
        'E512' => 'Unsupported card type',
        'E513' => 'Transaction type not allowed for this type of card',
        'E514' => 'Transaction declined by card issuer',
        'E515' => 'Implausible card data',
        'E516' => 'Incorrect PIN',
        'E517' => 'Transaction not allowed for cardholder',
        'E518' => 'The amount exceeds the card limit',
        'E600' => 'Transaction declined during ApplePay/GooglePay payment process',
        'E601' => 'Incorrect ApplePay or GooglePay configuration',
        'E620' => 'Transaction declined during PayPal payment process',
        'E621' => 'Transaction declined during PayPal payment process: invalid currency',
        'E640' => 'Bizum transaction declined after three authentication attempts',
        'E641' => 'Bizum transaction declined due to failed authorization',
        'E642' => 'Bizum transaction declined due to insufficient funds',
        'E643' => 'Bizum transaction canceled: the user does not want to continue',
        'E644' => 'Bizum transaction rejected by destination bank',
        'E645' => 'Bizum transaction rejected by origin bank',
        'E646' => 'Bizum transaction rejected by processor',
        'E647' => 'Bizum transaction failed while connecting with processor. Please try again',
        'E648' => 'Bizum transaction failed, payee is not found',
        'E649' => 'Bizum transaction failed, payer is not found',
        'E650' => 'Bizum REST not implemented',
        'E651' => 'Bizum transaction declined due to failed authentication',
        'E652' => 'The customer has disabled Bizum, please use another payment method',
        'E680' => 'Transaction declined during ClickToPay payment process',
        'E681' => 'Incorrect ClickToPay configuration',
        'E700' => 'Transaction declined during Cofidis payment process'
    ];

    /**
     * Get a localized status message for a status code
     *
     * @param string $statusCode
     * @return Phrase
     */
    public function getStatusMessage(string $statusCode): Phrase
    {
        $message = self::STATUS_CODES[$statusCode] ?? 'Unknown status code: ' . $statusCode;

        return __($message);
    }

    /**
     * Check if a status code indicates success
     *
     * @param string|null $statusCode
     * @return bool
     */
    public function isSuccessCode(?string $statusCode): bool
    {
        return $statusCode === 'E000';
    }

    /**
     * Check if a status code indicates an error
     *
     * @param string|null $statusCode
     * @return bool
     */
    public function isErrorCode(?string $statusCode): bool
    {
        return $statusCode !== null && $statusCode !== 'E000';
    }

    /**
     * Get all status codes and their localized messages
     *
     * @return array
     */
    public function getAllStatusCodes(): array
    {
        $localizedStatuses = [];
        foreach (self::STATUS_CODES as $code => $message) {
            $localizedStatuses[$code] = __($message);
        }

        return $localizedStatuses;
    }

    /**
     * Group status codes by category
     *
     * @return array
     */
    public function getStatusCodesByCategory(): array
    {
        $categories = [
            'general' => [],  // E000, E999
            'configuration' => [],  // E1xx
            'transaction' => [],  // E2xx
            'security' => [],  // E3xx
            'card' => [],  // E5xx
            'digital_wallets' => [],  // E6xx (Apple Pay, Google Pay)
            'alternative_methods' => []  // E64x-E69x (Bizum, etc)
        ];

        foreach (self::STATUS_CODES as $code => $message) {
            $prefix = substr($code, 0, 2);
            $secondDigit = substr($code, 1, 1);

            if ($code === 'E000' || $code === 'E999') {
                $categories['general'][$code] = __($message);
            } elseif ($prefix === 'E1') {
                $categories['configuration'][$code] = __($message);
            } elseif ($prefix === 'E2') {
                $categories['transaction'][$code] = __($message);
            } elseif ($prefix === 'E3') {
                $categories['security'][$code] = __($message);
            } elseif ($prefix === 'E5') {
                $categories['card'][$code] = __($message);
            } elseif ($code === 'E600' || $code === 'E601' || $code === 'E680' || $code === 'E681') {
                $categories['digital_wallets'][$code] = __($message);
            } elseif ($prefix === 'E6') {
                $categories['alternative_methods'][$code] = __($message);
            }
        }

        return $categories;
    }

    /**
     * Extract status code from payment data
     *
     * @param array $data
     * @return string|null
     */
    public function extractStatusCodeFromData(array $data): ?string
    {
        if (isset($data['statusCode'])) {
            return $data['statusCode'];
        }

        if (isset($data['status_code'])) {
            return $data['status_code'];
        }

        if (isset($data['response']) && is_array($data['response'])) {
            if (isset($data['response']['statusCode'])) {
                return $data['response']['statusCode'];
            }
        }

        if (isset($data['original_payment']) &&
                method_exists($data['original_payment'], 'getStatusCode')) {
            return $data['original_payment']->getStatusCode();
        }

        return null;
    }

    /**
     * Extract status message from payment data
     *
     * @param array $data
     * @return string|null
     */
    public function extractStatusMessageFromData(array $data): ?string
    {
        if (isset($data['statusMessage'])) {
            return $data['statusMessage'];
        }

        if (isset($data['status_message'])) {
            return $data['status_message'];
        }

        if (isset($data['message'])) {
            return $data['message'];
        }

        if (isset($data['response']) && is_array($data['response'])) {
            if (isset($data['response']['statusMessage'])) {
                return $data['response']['statusMessage'];
            }
            if (isset($data['response']['message'])) {
                return $data['response']['message'];
            }
        }

        if (isset($data['original_payment']) &&
                method_exists($data['original_payment'], 'getStatusMessage')) {
            return $data['original_payment']->getStatusMessage();
        }

        return null;
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monolog\Logger as MonologLogger;

/**
 * Enhanced logger for MONEI payment operations
 *
 * Provides standardized logging with consistent formatting for payment operations,
 * API calls, and error handling throughout the module.
 */
class Logger extends MonologLogger
{
    public const LOG_FILE_PATH = '/var/log/monei.log';

    /**
     * Options for JSON encoding in logs
     */
    public const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Mask for sensitive data in logs
     */
    private const MASKED_VALUE = '*** REDACTED ***';

    /**
     * List of sensitive fields that should be masked in logs
     *
     * @var array
     */
    private array $sensitiveFields = [
        'api_key',
        'apiKey',
        'password',
        'secret',
        'token',
        'credential',
        'cvv',
        'cvc',
        'card_number',
        'cardNumber',
        'pan',
        'security_code',
        'securityCode'
    ];

    /**
     * Log API request with standardized format
     *
     * @param string $operation Operation name
     * @param array|object $data Request data
     * @return void
     */
    public function logApiRequest(string $operation, $data = []): void
    {
        if (empty($data)) {
            $this->debug("API Request: {$operation}", []);

            return;
        }

        // Convert object to array if necessary
        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } elseif (method_exists($data, '__toArray')) {
                $data = $data->__toArray();
            } else {
                $data = (array)$data;
            }
        }

        // Sanitize sensitive data
        $sanitizedData = $this->sanitizeData($data);

        // Pretty-print the JSON for the log
        $prettyJson = json_encode(['request' => $sanitizedData], self::JSON_OPTIONS);
        $this->debug("API Request: {$operation} " . $prettyJson);
    }

    /**
     * Log API response with standardized format
     *
     * @param string $operation Operation name
     * @param array|object $data Response data
     * @return void
     */
    public function logApiResponse(string $operation, $data): void
    {
        // Convert object to array if necessary
        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } elseif (method_exists($data, '__toArray')) {
                $data = $data->__toArray();
            } else {
                $data = (array)$data;
            }
        }

        // Sanitize sensitive data
        $sanitizedData = $this->sanitizeData($data);

        // Pretty-print the JSON for the log
        $prettyJson = json_encode(['response' => $sanitizedData], self::JSON_OPTIONS);
        $this->debug("API Response: {$operation} " . $prettyJson);
    }

    /**
     * Log API error with standardized format
     *
     * @param string $operation Operation name
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public function logApiError(string $operation, string $message, array $context = []): void
    {
        // Sanitize sensitive data
        $sanitizedData = $this->sanitizeData($context);

        // Pretty-print the JSON for the log
        $prettyJson = json_encode($sanitizedData, self::JSON_OPTIONS);
        $this->critical("API Error: {$operation} - {$message} " . $prettyJson);
    }

    /**
     * Log payment event with standardized format
     *
     * @param string $type Event type (create, capture, refund, etc.)
     * @param string $orderId Order ID
     * @param ?string $paymentId Payment ID
     * @param array|object $data Additional data
     * @return void
     */
    public function logPaymentEvent(string $type, string $orderId, ?string $paymentId = null, $data = []): void
    {
        $context = [
            'order_id' => $orderId,
            'payment_id' => $paymentId
        ];

        if (!empty($data)) {
            // Sanitize sensitive data
            $context['data'] = $this->sanitizeData($data);
        }

        // Pretty-print the JSON for the log
        $prettyJson = json_encode($context, self::JSON_OPTIONS);
        $this->info("Payment {$type} " . $prettyJson);
    }

    /**
     * {@inheritDoc}
     */
    public function debug($message, array $context = []): void
    {
        parent::debug($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function info($message, array $context = []): void
    {
        parent::info($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function warning($message, array $context = []): void
    {
        parent::warning($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function error($message, array $context = []): void
    {
        parent::error($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function critical($message, array $context = []): void
    {
        parent::critical($message, $context);
    }

    /**
     * Check if a string is a valid JSON
     *
     * @param string $string
     * @return bool
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize sensitive data for logging
     *
     * @param array|object $data
     * @return array
     */
    private function sanitizeData($data): array
    {
        // If data is an object, convert it to an array
        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } elseif (method_exists($data, '__toArray')) {
                $data = $data->__toArray();
            } else {
                $data = (array)$data;
            }
        }

        // If not an array by now, return empty array
        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $key => $value) {
            // Mask sensitive fields - ensure key is a string before using strtolower
            if (is_string($key) && in_array(strtolower($key), array_map('strtolower', $this->sensitiveFields))) {
                $data[$key] = self::MASKED_VALUE;

                continue;
            }

            // Recurse into arrays and objects
            if (is_array($value) || is_object($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }
}

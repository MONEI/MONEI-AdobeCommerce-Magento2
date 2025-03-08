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
     * @param array $data Request data
     * @return void
     */
    public function logApiRequest(string $operation, array $data = []): void
    {
        $this->debug(
            "API Request: {$operation}",
            empty($data) ? [] : ['request' => $this->sanitizeData($data)]
        );
    }

    /**
     * Log API response with standardized format
     *
     * @param string $operation Operation name
     * @param array $data Response data
     * @return void
     */
    public function logApiResponse(string $operation, array $data): void
    {
        $this->debug("API Response: {$operation}", ['response' => $this->sanitizeData($data)]);
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
        $this->critical("API Error: {$operation} - {$message}", $this->sanitizeData($context));
    }

    /**
     * Log payment event with standardized format
     *
     * @param string $type Event type (create, capture, refund, etc.)
     * @param string $orderId Order ID
     * @param ?string $paymentId Payment ID
     * @param array $data Additional data
     * @return void
     */
    public function logPaymentEvent(string $type, string $orderId, ?string $paymentId = null, array $data = []): void
    {
        $context = [
            'order_id' => $orderId,
            'payment_id' => $paymentId
        ];

        if (!empty($data)) {
            $context['data'] = $this->sanitizeData($data);
        }

        $this->info("Payment {$type}", $context);
    }

    /**
     * {@inheritDoc}
     */
    public function debug($message, array $context = []): void
    {
        parent::debug($message, $this->formatContext($context));
    }

    /**
     * {@inheritDoc}
     */
    public function info($message, array $context = []): void
    {
        parent::info($message, $this->formatContext($context));
    }

    /**
     * {@inheritDoc}
     */
    public function warning($message, array $context = []): void
    {
        parent::warning($message, $this->formatContext($context));
    }

    /**
     * {@inheritDoc}
     */
    public function error($message, array $context = []): void
    {
        parent::error($message, $this->formatContext($context));
    }

    /**
     * {@inheritDoc}
     */
    public function critical($message, array $context = []): void
    {
        parent::critical($message, $this->formatContext($context));
    }

    /**
     * Format context array for consistent logging
     *
     * @param array $context
     * @return array
     */
    private function formatContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $context[$key] = json_encode($value, self::JSON_OPTIONS);
            }
        }

        return $context;
    }

    /**
     * Sanitize sensitive data for logging
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            // Mask sensitive fields
            if (in_array(strtolower($key), array_map('strtolower', $this->sensitiveFields))) {
                $data[$key] = self::MASKED_VALUE;

                continue;
            }

            // Recurse into arrays
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }
}

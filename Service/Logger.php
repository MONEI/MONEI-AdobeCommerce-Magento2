<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Model\Config\Source\LogLevel;
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

        $prettyJson = $this->formatJsonForLog(['request' => $data]);
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
        $prettyJson = $this->formatJsonForLog(['response' => $data]);
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
        $prettyJson = $this->formatJsonForLog($context);
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
            $context['data'] = $data;
        }

        $prettyJson = $this->formatJsonForLog($context);
        $this->info("Payment {$type} " . $prettyJson);
    }

    /**
     * Format data as pretty JSON for logging
     *
     * @param array|object $data Data to format
     * @return string Formatted JSON string
     */
    private function formatJsonForLog($data): string
    {
        if (empty($data)) {
            return '{}';
        }

        try {
            return json_encode($data, self::JSON_OPTIONS) ?: '{}';
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Unable to encode data to JSON', 'message' => $e->getMessage()]);
        }
    }
}

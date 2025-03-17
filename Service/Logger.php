<?php

/**
 * MONEI Payment Logger
 *
 * @category  Payment
 * @package   Monei_MoneiPayment
 * @author    Monei Team <dev@monei.com>
 * @copyright Copyright © 2023 Monei (https://monei.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://monei.com
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Service\Logger\Handler;
use Monolog\Logger as MonologLogger;

/**
 * Enhanced logger for MONEI payment operations
 *
 * Provides standardized logging with consistent formatting for payment operations,
 * API calls, and error handling throughout the module.
 *
 * @category Payment
 * @package  Monei_MoneiPayment
 * @author   Monei Team <dev@monei.com>
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://monei.com
 */
class Logger extends MonologLogger
{
    public const LOG_FILE_PATH = '/var/log/monei.log';

    /**
     * Options for JSON encoding in logs
     */
    public const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Logger constructor
     *
     * @param Handler $handler Logger handler instance
     * @param string  $name    Channel name
     */
    public function __construct(
        Handler $handler,
        string $name = 'monei'
    ) {
        parent::__construct($name);
        $this->pushHandler($handler);
    }

    /**
     * Log API request with standardized format
     *
     * @param string       $operation Operation name
     * @param array|object $data      Request data
     *
     * @return void
     */
    public function logApiRequest(string $operation, $data = []): void
    {
        if (empty($data)) {
            $this->debug("[ApiRequest] {$operation}", []);

            return;
        }

        $prettyJson = $this->_formatJsonForLog(['request' => $data]);
        $this->debug("[ApiRequest] {$operation} " . $prettyJson);
    }

    /**
     * Log API response with standardized format
     *
     * @param string       $operation Operation name
     * @param array|object $data      Response data
     *
     * @return void
     */
    public function logApiResponse(string $operation, $data): void
    {
        $prettyJson = $this->_formatJsonForLog(['response' => $data]);
        $this->debug("[ApiResponse] {$operation} " . $prettyJson);
    }

    /**
     * Log API error with standardized format
     *
     * @param string $operation Operation name
     * @param string $message   Error message
     * @param array  $context   Additional context
     *
     * @return void
     */
    public function logApiError(string $operation, string $message, array $context = []): void
    {
        $prettyJson = $this->_formatJsonForLog($context);
        $this->critical("[ApiError] {$operation} - {$message} " . $prettyJson);
    }

    /**
     * Log payment event with standardized format
     *
     * @param string       $type      Event type (create, capture, refund, etc.)
     * @param string       $orderId   Order ID
     * @param ?string      $paymentId Payment ID
     * @param array|object $data      Additional data
     *
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

        $prettyJson = $this->_formatJsonForLog($context);
        $this->info("[Payment] {$type} " . $prettyJson);
    }

    /**
     * Format data as pretty JSON for logging
     *
     * @param array|object $data Data to format
     *
     * @return string Formatted JSON string
     */
    private function _formatJsonForLog($data): string
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

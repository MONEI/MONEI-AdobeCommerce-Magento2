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
use Psr\Log\LoggerInterface;

/**
 * Enhanced logger for MONEI payment operations
 *
 * Provides standardized logging with consistent formatting for payment operations,
 * API calls, and error handling throughout the module.
 *
 * Compatible with both Monolog 2.x and 3.x by using composition instead of inheritance.
 *
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://monei.com
 */
class Logger implements LoggerInterface
{
    public const LOG_FILE_PATH = '/var/log/monei.log';

    /**
     * Options for JSON encoding in logs
     */
    public const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @var MonologLogger
     */
    private MonologLogger $logger;

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
        $this->logger = new MonologLogger($name);
        $this->logger->pushHandler($handler);
    }

    /**
     * System is unusable.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
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
            $result = json_encode($data, self::JSON_OPTIONS);
            if ($result === false) {
                return 'Unable to encode data to JSON: ' . json_last_error_msg();
            }
            return $result;
        } catch (\Throwable $e) {
            return 'Unable to encode data to JSON: ' . $e->getMessage();
        }
    }

    /**
     * Get the internal Monolog logger instance
     *
     * @return MonologLogger
     */
    public function getMonologInstance(): MonologLogger
    {
        return $this->logger;
    }
}
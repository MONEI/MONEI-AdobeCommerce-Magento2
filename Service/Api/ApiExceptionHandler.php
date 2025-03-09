<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\ApiException;
use Monei\MoneiPayment\Service\Logger;

/**
 * Centralized handler for MONEI API exceptions
 *
 * Standardizes error handling for all MONEI API interactions by providing
 * consistent error messages based on HTTP status codes and API error types.
 */
class ApiExceptionHandler
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle API exception and transform it into LocalizedException with context-aware messages
     *
     * @param ApiException $e The API exception from MONEI SDK
     * @param string $operation The operation name (for context in logs)
     * @param array $context Additional context for logging
     * @return never Always throws an exception
     * @throws LocalizedException
     */
    public function handle(ApiException $e, string $operation, array $context = []): never
    {
        $statusCode = $e->getCode();
        $errorBody = null;

        try {
            // Try to parse the response body for more detailed error information
            if ($e->getResponseBody()) {
                $errorBody = json_decode($e->getResponseBody(), true);
            }
        } catch (\Exception $parseException) {
            $this->logger->warning(
                'Failed to parse API error response: ' . $parseException->getMessage(),
                ['response_body' => $e->getResponseBody()]
            );
        }

        // Extract error details
        $errorMessage = $errorBody['message'] ?? $e->getMessage();
        $errorCode = $errorBody['code'] ?? $statusCode;

        $errorContext = array_merge($context, [
            'status_code' => $statusCode,
            'error_code' => $errorCode,
            'error_message' => $errorMessage
        ]);

        $this->logger->logApiError($operation, $errorMessage, $errorContext);

        // Provide user-friendly error messages based on HTTP status code
        switch ($statusCode) {
            case 400:
                throw new LocalizedException(
                    __('Invalid request data: %1', $errorMessage)
                );
            case 401:
                throw new LocalizedException(
                    __('Authentication error: Please check your MONEI API credentials')
                );
            case 402:
                throw new LocalizedException(
                    __('Payment required: %1', $errorMessage)
                );
            case 403:
                throw new LocalizedException(
                    __('Access denied: Your account does not have permission for this operation')
                );
            case 404:
                throw new LocalizedException(
                    __('Resource not found: %1', $errorMessage)
                );
            case 409:
                throw new LocalizedException(
                    __('Operation conflict: %1', $errorMessage)
                );
            case 422:
                throw new LocalizedException(
                    __('Validation error: %1', $errorMessage)
                );
            case 429:
                throw new LocalizedException(
                    __('Too many requests: Please try again later')
                );
            case 500:
            case 502:
            case 503:
            case 504:
                throw new LocalizedException(
                    __('MONEI payment service is currently unavailable. Please try again later.')
                );
            default:
                throw new LocalizedException(
                    __($errorMessage)
                );
        }
    }
}

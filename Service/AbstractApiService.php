<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;

/**
 * Abstract base class for MONEI API services
 *
 * Provides common functionality for all MONEI API service classes,
 * reducing boilerplate code and standardizing error handling.
 */
abstract class AbstractApiService
{
    /**
     * @var \Monei\MoneiPayment\Service\Logger
     */
    protected $logger;

    /**
     * @param \Monei\MoneiPayment\Service\Logger $logger
     */
    public function __construct(
        \Monei\MoneiPayment\Service\Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Execute API call with standardized error handling
     *
     * @param string $methodName Name of the method being called (for logging)
     * @param callable $apiCall Callable that performs the API call
     * @param array $logData Additional data to log with the request
     *
     * @return array Response from the API
     * @throws LocalizedException
     */
    protected function executeApiCall(string $methodName, callable $apiCall, array $logData = []): array
    {
        $this->logger->debug("[Method] {$methodName}");

        if (!empty($logData)) {
            $this->logger->debug('[Request data]', $logData);
        }

        try {
            $response = $apiCall();

            $this->logger->debug('[API Response]', [
                'data' => is_array($response) ? $response : ['response' => (string) $response]
            ]);

            return is_array($response) ? $response : ['response' => $response];
        } catch (LocalizedException $e) {
            $this->logger->critical("[API Error] {$e->getMessage()}");

            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical("[Error] {$e->getMessage()}");

            throw new LocalizedException(__('Failed to execute %1: %2', $methodName, $e->getMessage()));
        }
    }

    /**
     * Validate required parameters
     *
     * @param array $data Data to validate
     * @param array $requiredParams List of required parameters
     * @param array $customValidators Custom validation callbacks in format [paramName => callable]
     *
     * @throws LocalizedException If validation fails
     */
    protected function validateParams(array $data, array $requiredParams, array $customValidators = []): void
    {
        foreach ($requiredParams as $param) {
            if (!isset($data[$param]) || $data[$param] === null) {
                throw new LocalizedException(__('Required parameter "%1" is missing or empty.', $param));
            }
        }

        foreach ($customValidators as $param => $validator) {
            if (isset($data[$param]) && !$validator($data[$param])) {
                throw new LocalizedException(__('Parameter "%1" failed validation.', $param));
            }
        }
    }

    /**
     * Convert camelCase keys to snake_case in an array
     *
     * @param array $data Input data with possible camelCase keys
     * @return array Data with all keys in snake_case
     */
    protected function convertKeysToSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            // Convert camelCase to snake_case
            $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));

            // If value is an array, recursively convert its keys too
            if (is_array($value)) {
                $value = $this->convertKeysToSnakeCase($value);
            }

            $result[$snakeKey] = $value;
        }

        return $result;
    }
}

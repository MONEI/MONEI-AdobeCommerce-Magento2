<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\ApiException;
use Monei\MoneiClient;

/**
 * Abstract base class for MONEI API services
 *
 * Provides common functionality for all MONEI API service classes,
 * reducing boilerplate code and standardizing error handling.
 */
abstract class AbstractApiService
{
    /**
     * Logger for service operations
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Exception handler for MONEI API errors
     *
     * @var ApiExceptionHandler|null
     */
    protected ?ApiExceptionHandler $exceptionHandler = null;

    /**
     * MONEI API client factory
     *
     * @var MoneiApiClient|null
     */
    protected ?MoneiApiClient $apiClient = null;

    /**
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler|null $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient|null $apiClient MONEI API client factory
     */
    public function __construct(
        Logger $logger,
        ?ApiExceptionHandler $exceptionHandler = null,
        ?MoneiApiClient $apiClient = null
    ) {
        $this->logger = $logger;
        $this->exceptionHandler = $exceptionHandler;
        $this->apiClient = $apiClient;
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
        // Log request with standardized format
        $this->logger->logApiRequest($methodName, $logData);

        try {
            // Execute the API call
            $response = $apiCall();

            // Convert response to array if needed
            $result = is_array($response) ? $response : ['response' => $response];

            // Log response with standardized format
            $this->logger->logApiResponse($methodName, $result);

            return $result;
        } catch (LocalizedException $e) {
            $this->logger->logApiError($methodName, $e->getMessage(), $logData);

            throw $e;
        } catch (\Exception $e) {
            $this->logger->logApiError($methodName, $e->getMessage(), [
                'exception' => get_class($e),
                'request_data' => $logData
            ]);

            throw new LocalizedException(__('Failed to execute %1: %2', $methodName, $e->getMessage()));
        }
    }

    /**
     * Execute MONEI SDK call with standardized error handling
     *
     * This is the preferred method for making SDK calls when using the standardized pattern.
     *
     * @param string $operation Name of the operation (for logging)
     * @param callable $sdkCall Callable that performs the API call, receives MoneiClient as parameter
     * @param array $logContext Additional context data for logging
     * @param int|null $storeId Store ID to use for configuration, defaults to current store
     *
     * @return mixed Raw response from the API (typically an SDK object)
     * @throws LocalizedException If API call fails
     * @throws \InvalidArgumentException If required dependencies are missing
     */
    protected function executeMoneiSdkCall(
        string $operation,
        callable $sdkCall,
        array $logContext = [],
        ?int $storeId = null
    ) {
        // Verify required dependencies
        if (!$this->apiClient || !$this->exceptionHandler) {
            throw new \InvalidArgumentException(
                'Cannot execute MONEI SDK call: MoneiApiClient or ApiExceptionHandler is missing.'
            );
        }

        // Log request with standardized format
        $this->logger->logApiRequest($operation, $logContext);

        try {
            // Get the SDK client
            $moneiSdk = $this->apiClient->getMoneiSdk($storeId);

            // Execute the SDK call
            $response = $sdkCall($moneiSdk);

            // For logging purposes, we'll convert to array but return the original object
            $responseArray = $this->apiClient->convertResponseToArray($response);

            // Log success with standardized format
            $this->logger->logApiResponse($operation, $responseArray);

            return $response;
        } catch (ApiException $e) {
            // Let the exception handler process the API exception
            $this->exceptionHandler->handle($e, $operation, $logContext);
        } catch (NoSuchEntityException $e) {
            $this->logger->logApiError($operation, "Store configuration error: {$e->getMessage()}", $logContext);

            throw new LocalizedException(__('Store configuration issue: %1', $e->getMessage()));
        } catch (\Exception $e) {
            $this->logger->logApiError($operation, "Unexpected error: {$e->getMessage()}", [
                'exception' => get_class($e),
                'context' => $logContext
            ]);

            throw new LocalizedException(__('An unexpected error occurred: %1', $e->getMessage()));
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

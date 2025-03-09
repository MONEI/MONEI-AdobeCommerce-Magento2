<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;

/**
 * Monei get payment service class using the official MONEI PHP SDK.
 *
 * Retrieves payment details by ID from the MONEI API.
 */
class GetPayment extends AbstractApiService implements GetPaymentInterface
{
    /**
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient $apiClient API client factory for MONEI SDK
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
    }

    /**
     * Execute a payment retrieval request to the Monei API.
     *
     * Retrieves payment details by ID from the Monei API using the official SDK.
     *
     * @param string $payment_id The ID of the payment to retrieve
     *
     * @return Payment Response from the API with payment details as Payment object
     * @throws LocalizedException If the payment cannot be retrieved
     */
    public function execute(string $payment_id): Payment
    {
        if (empty($payment_id)) {
            throw new LocalizedException(__('Payment ID is required to retrieve payment details'));
        }

        // Log the request parameters for easier debugging
        $this->logger->debug('API Request: getPayment', [
            'request' => [
                'payment_id' => $payment_id
            ]
        ]);

        // Use standardized SDK call pattern with the executeMoneiSdkCall method
        $response = $this->executeMoneiSdkCall(
            'getPayment',
            function (MoneiClient $moneiSdk) use ($payment_id) {
                return $moneiSdk->payments->get($payment_id);
            },
            ['payment_id' => $payment_id]
        );

        // SDK Payment objects are properly structured but have magic getters/setters
        // Let's make sure we can access them - try to access via accessor methods
        $responseValid = false;
        try {
            $responseValid = !empty($response->getId()) && !empty($response->getOrderId());
        } catch (\Exception $e) {
            $this->logger->error('Error accessing Payment properties: ' . $e->getMessage());
            // Will continue and throw the exception below
        }

        if (!$responseValid) {
            $this->logger->error('Invalid payment response', [
                'payment_id' => $payment_id,
                'response_type' => is_object($response) ? get_class($response) : gettype($response)
            ]);
            throw new LocalizedException(__('Invalid payment response from API'));
        }

        return $response;
    }
}

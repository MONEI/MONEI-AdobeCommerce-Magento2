<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;

/**
 * Monei get payment service class using the official MONEI PHP SDK.
 *
 * Retrieves payment details by ID from the MONEI API.
 */
class GetPayment extends AbstractApiService implements GetPaymentInterface
{
    /**
     * Static cache of payment data to avoid repeated API calls within a single request
     * for payments in terminal states (like SUCCEEDED, FAILED, CANCELED)
     *
     * @var array<string, array{data: Payment, timestamp: int}>
     */
    private static array $paymentCache = [];

    /**
     * Cache lifetime in seconds (5 minutes - longer than paymentMethods since terminal status won't change)
     */
    private const CACHE_LIFETIME = 300;

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
     * Caches payment data for terminal states to reduce redundant API calls.
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

        // Create data array for logging
        $data = ['payment_id' => $payment_id];

        // Check if payment is in cache and not expired
        $currentTime = time();
        if (
            isset(self::$paymentCache[$payment_id]) &&
            ($currentTime - self::$paymentCache[$payment_id]['timestamp'] < self::CACHE_LIFETIME)
        ) {
            $this->logger->debug('Using cached payment data', ['payment_id' => $payment_id]);
            return self::$paymentCache[$payment_id]['data'];
        }

        // Get payment data from API
        $payment = $this->executeMoneiSdkCall(
            'getPayment',
            function (MoneiClient $moneiSdk) use ($payment_id) {
                return $moneiSdk->payments->get($payment_id);
            },
            $data
        );

        // Cache payment data if it's in a terminal state
        if (Status::isFinalStatus($payment->getStatus())) {
            $this->logger->debug('Caching payment in terminal state', [
                'payment_id' => $payment_id,
                'status' => $payment->getStatus()
            ]);

            self::$paymentCache[$payment_id] = [
                'data' => $payment,
                'timestamp' => $currentTime
            ];
        }

        return $payment;
    }
}

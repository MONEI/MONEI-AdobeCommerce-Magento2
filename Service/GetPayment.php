<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiClient;

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

        return $this->executeMoneiSdkCall(
            'getPayment',
            function (MoneiClient $moneiSdk) use ($payment_id) {
                return $moneiSdk->payments->get($payment_id);
            },
            ['payment_id' => $payment_id]
        );
    }
}

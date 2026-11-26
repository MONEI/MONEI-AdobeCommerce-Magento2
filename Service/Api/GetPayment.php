<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
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
     * @var FetchedPayments
     */
    private FetchedPayments $fetchedPayments;

    /**
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient $apiClient API client factory for MONEI SDK
     * @param FetchedPayments $fetchedPayments Payments the callback verifier fetched in this request
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient,
        FetchedPayments $fetchedPayments
    ) {
        $this->fetchedPayments = $fetchedPayments;
        parent::__construct($logger, $exceptionHandler, $apiClient);
    }

    /**
     * Execute a payment retrieval request to the Monei API.
     *
     * Retrieves payment details by ID from the Monei API using the official SDK.
     *
     * @param string $payment_id The ID of the payment to retrieve
     * @param int|null $storeId The store whose API key fetches it; the current store when null
     *
     * @return Payment Response from the API with payment details as Payment object
     * @throws LocalizedException If the payment cannot be retrieved
     */
    public function execute(string $payment_id, ?int $storeId = null): Payment
    {
        if (empty($payment_id)) {
            throw new LocalizedException(__('Payment ID is required to retrieve payment details'));
        }
        $fetched = $this->fetchedPayments->get($payment_id);
        if ($fetched !== null) {
            return $fetched;
        }

        // Create data array for logging
        $data = ['payment_id' => $payment_id];

        return $this->executeMoneiSdkCall(
            'getPayment',
            function (MoneiClient $moneiSdk) use ($payment_id) {
                return $moneiSdk->payments->get($payment_id);
            },
            $data,
            $storeId
        );
    }
}

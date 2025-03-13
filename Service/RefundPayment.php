<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\Model\PaymentRefundReason;
use Monei\Model\RefundPaymentRequest;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiClient;

/**
 * Monei refund payment service class using the official MONEI PHP SDK.
 */
class RefundPayment extends AbstractApiService implements RefundPaymentInterface
{
    /**
     * @var array
     */
    private $requiredArguments = [
        'payment_id',
        'refund_reason',
        'amount',
    ];

    /**
     * @var array
     */
    private $refundReasons = [
        PaymentRefundReason::DUPLICATED,
        PaymentRefundReason::FRAUDULENT,
        PaymentRefundReason::REQUESTED_BY_CUSTOMER,
    ];

    /**
     * @param Logger $logger
     * @param ApiExceptionHandler $exceptionHandler
     * @param MoneiApiClient $apiClient
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
    }

    /**
     * Execute payment refund request.
     *
     * @param array $data Payment data including payment_id, refund_reason, and amount
     *
     * @return Payment Response from the Monei API as Payment object
     * @throws LocalizedException If refund fails
     */
    public function execute(array $data): Payment
    {
        // Convert any camelCase keys to snake_case to ensure consistency
        $data = $this->convertKeysToSnakeCase($data);

        // Validate the request parameters
        $this->validateParams($data, $this->requiredArguments, [
            'refund_reason' => function ($value) {
                return in_array($value, $this->refundReasons, true);
            },
            'amount' => function ($value) {
                return is_numeric($value);
            }
        ]);

        // Create refund request with SDK model
        $refundRequest = new RefundPaymentRequest();

        // Set amount in cents
        if (isset($data['amount'])) {
            $refundRequest->setAmount((int) ($data['amount'] * 100));
        }

        // Set refund reason using the SDK enum
        $refundRequest->setRefundReason($data['refund_reason']);

        // Use standardized SDK call pattern with the executeMoneiSdkCall method
        return $this->executeMoneiSdkCall(
            'refundPayment',
            function (MoneiClient $moneiSdk) use ($data, $refundRequest) {
                return $moneiSdk->payments->refund($data['payment_id'], $refundRequest);
            },
            $data
        );
    }
}

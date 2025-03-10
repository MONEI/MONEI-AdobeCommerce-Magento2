<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\ApiException;
use Monei\Model\Payment;
use Monei\Model\PaymentRefundReason;
use Monei\Model\RefundPaymentRequest;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;

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
     * @var MoneiApiClient
     */
    private $moneiApiClient;

    /**
     * @param Logger $logger
     * @param MoneiApiClient $moneiApiClient
     */
    public function __construct(
        Logger $logger,
        MoneiApiClient $moneiApiClient
    ) {
        parent::__construct($logger);
        $this->moneiApiClient = $moneiApiClient;
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

        return $this->executeApiCall(__METHOD__, function () use ($data) {
            // Validate the request parameters
            $this->validateParams($data, $this->requiredArguments, [
                'refund_reason' => function ($value) {
                    return in_array($value, $this->refundReasons, true);
                },
                'amount' => function ($value) {
                    return is_numeric($value);
                }
            ]);

            try {
                // Get the SDK client
                $moneiSdk = $this->moneiApiClient->getMoneiSdk($data['store_id'] ?? null);

                // Create refund request with SDK model
                $refundRequest = new RefundPaymentRequest();

                // Set amount in cents
                if (isset($data['amount'])) {
                    $refundRequest->setAmount((int) ($data['amount'] * 100));
                }

                // Set refund reason using the SDK enum
                $refundRequest->setRefundReason($data['refund_reason']);

                // Refund the payment using the SDK and request object
                $payment = $moneiSdk->payments->refund($data['payment_id'], $refundRequest);

                // Log refund reason - just for reference
                $this->logger->debug('Refund completed', [
                    'payment_id' => $data['payment_id'],
                    'refund_reason' => $data['refund_reason']
                ]);

                return $payment;
            } catch (ApiException $e) {
                $this->logger->critical('[API Error] ' . $e->getMessage());

                throw new LocalizedException(__('Failed to refund payment with MONEI API: %1', $e->getMessage()));
            }
        }, ['refundData' => $data]);
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

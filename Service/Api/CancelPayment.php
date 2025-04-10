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
use Monei\Model\CancelPaymentRequest;
use Monei\Model\Payment;
use Monei\Model\PaymentCancellationReason;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;

/**
 * Monei cancel payment service class using the official MONEI PHP SDK.
 */
class CancelPayment extends AbstractApiService implements CancelPaymentInterface
{
    /**
     * @var array
     */
    private $requiredArguments = [
        'payment_id',
        'cancellation_reason',
    ];

    /**
     * @var array
     */
    private $cancellationReasons = [
        PaymentCancellationReason::DUPLICATED,
        PaymentCancellationReason::FRAUDULENT,
        PaymentCancellationReason::REQUESTED_BY_CUSTOMER,
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
     * Execute payment cancellation request.
     *
     * @param array $data Payment data including payment_id and cancellation_reason
     *
     * @return Payment MONEI SDK Payment object
     * @throws LocalizedException If the payment cannot be cancelled
     */
    public function execute(array $data): Payment
    {
        // Convert any camelCase keys to snake_case to ensure consistency
        $data = $this->convertKeysToSnakeCase($data);

        // Validate the request parameters
        $this->validateParams($data, $this->requiredArguments, [
            'cancellation_reason' => function ($value) {
                return in_array($value, $this->cancellationReasons, true);
            }
        ]);

        // Create cancel request with SDK model
        $cancelRequest = new CancelPaymentRequest();

        // Set cancellation reason using the SDK enum
        $cancelRequest->setCancellationReason($data['cancellation_reason']);

        // Use standardized SDK call pattern with the executeMoneiSdkCall method
        return $this->executeMoneiSdkCall(
            'cancelPayment',
            function (MoneiClient $moneiSdk) use ($data, $cancelRequest) {
                return $moneiSdk->payments->cancel($data['payment_id'], $cancelRequest);
            },
            $data
        );
    }
}

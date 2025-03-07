<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use OpenAPI\Client\Model\CancelPaymentRequest;
use OpenAPI\Client\Model\PaymentCancellationReason;
use OpenAPI\Client\ApiException;

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
     * Execute payment cancellation request.
     *
     * @param array $data Payment data including payment_id and cancellation_reason
     *
     * @return array Response from the Monei API
     */
    public function execute(array $data): array
    {
        return $this->executeApiCall(__METHOD__, function () use ($data) {
            // Validate the request parameters
            $this->validateParams($data, $this->requiredArguments, [
                'cancellation_reason' => function ($value) {
                    return in_array($value, $this->cancellationReasons, true);
                }
            ]);

            try {
                // Get the SDK client
                $moneiSdk = $this->moneiApiClient->getMoneiSdk();

                // Create cancel request with SDK model
                $cancelRequest = new CancelPaymentRequest();

                // Set cancellation reason using the SDK enum
                $cancelRequest->setCancellationReason($data['cancellation_reason']);

                // Cancel the payment using the SDK and request object
                $payment = $moneiSdk->payments->cancel($data['payment_id'], $cancelRequest);

                // Convert response to array and add cancellation reason for reference
                $response = $this->moneiApiClient->convertResponseToArray($payment);
                $response['cancellation_reason'] = $data['cancellation_reason'];

                return $response;
            } catch (ApiException $e) {
                $this->logger->critical('[API Error] ' . $e->getMessage());

                throw new LocalizedException(__('Failed to cancel payment with MONEI API: %1', $e->getMessage()));
            }
        }, ['cancelData' => $data]);
    }
}

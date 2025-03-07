<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use OpenAPI\Client\ApiException;

/**
 * Monei get payment service class using the official MONEI PHP SDK.
 */
class GetPayment extends AbstractApiService implements GetPaymentInterface
{
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
     * Execute a payment retrieval request to the Monei API.
     *
     * Retrieves payment details by ID from the Monei API using the official SDK.
     *
     * @param string $payment_id The ID of the payment to retrieve
     *
     * @return array Response from the API with payment details or error information
     * @throws LocalizedException
     */
    public function execute(string $payment_id): array
    {
        return $this->executeApiCall(__METHOD__, function () use ($payment_id) {
            try {
                // Get the SDK client directly
                $moneiSdk = $this->moneiApiClient->getMoneiSdk();

                // Use the SDK to get the payment with the correct get() method
                $payment = $moneiSdk->payments->get($payment_id);

                // Convert response to array
                return $this->moneiApiClient->convertResponseToArray($payment);
            } catch (ApiException $e) {
                $this->logger->critical('[API Error] ' . $e->getMessage());
                throw new LocalizedException(__('Failed to get payment from MONEI API: %1', $e->getMessage()));
            }
        }, ['payment_id' => $payment_id]);
    }
}

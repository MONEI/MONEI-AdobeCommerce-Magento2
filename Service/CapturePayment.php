<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\CapturePaymentRequest;

/**
 * Monei capture payment service class using the official MONEI PHP SDK.
 */
class CapturePayment extends AbstractApiService implements CapturePaymentInterface
{
    /**
     * List of required parameters for capture request.
     *
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'amount',
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
     * Execute a payment capture request to the Monei API.
     *
     * Captures an authorized payment using the official MONEI SDK.
     * Requires a payment ID and amount to capture.
     *
     * @param array $data Data for the capture request containing paymentId and amount
     *
     * @return array Response from the API with capture results or error details
     */
    public function execute(array $data): array
    {
        return $this->executeApiCall(__METHOD__, function () use ($data) {
            // Validate the request parameters
            $this->validateParams($data, $this->requiredArguments, [
                'amount' => function ($value) {
                    return is_numeric($value);
                }
            ]);

            try {
                // Get the SDK client
                $moneiSdk = $this->moneiApiClient->getMoneiSdk($data['storeId'] ?? null);

                // Create capture request with SDK model
                $captureRequest = new CapturePaymentRequest();

                // Set amount in cents
                if (isset($data['amount'])) {
                    $captureRequest->setAmount((int)round((float)$data['amount'] * 100));
                }

                // Capture the payment using the SDK and request object
                $payment = $moneiSdk->payments->capture($data['paymentId'], $captureRequest);

                // Convert response to array
                return $this->moneiApiClient->convertResponseToArray($payment);
            } catch (ApiException $e) {
                $this->logger->critical('[API Error] ' . $e->getMessage());
                throw new LocalizedException(__('Failed to capture payment with MONEI API: %1', $e->getMessage()));
            }
        }, ['captureData' => $data]);
    }

    /**
     * Validate request required parameters.
     *
     * Checks if all required parameters are present and have appropriate values.
     * Throws exceptions if validation fails.
     *
     * @param array $data Request data to validate
     *
     * @throws LocalizedException If validation fails
     */
    private function validate(array $data): void
    {
        foreach ($this->requiredArguments as $argument) {
            if (!isset($data[$argument]) || null === $data[$argument]) {
                throw new LocalizedException(
                    __('Required parameter "%1" is missing or empty.', $argument)
                );
            } elseif ('amount' === $argument && !is_numeric($data[$argument])) {
                throw new LocalizedException(
                    __('%1 should be numeric value', $argument)
                );
            }
        }
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\ConfirmPaymentRequest;
use Monei\Model\Payment;
use Monei\Model\PaymentBillingDetails;
use Monei\Model\PaymentCustomer;
use Monei\Model\PaymentShippingDetails;
use Monei\MoneiPayment\Api\Service\ConfirmPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiClient;

/**
 * Monei confirm payment service class using the official MONEI PHP SDK.
 */
class ConfirmPayment extends AbstractApiService implements ConfirmPaymentInterface
{
    /**
     * List of required parameters for confirm request.
     *
     * @var array
     */
    private $requiredArguments = [
        'paymentId',
        'paymentToken'
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
     * Execute a payment confirm request to the Monei API.
     *
     * Confirms a payment using the official MONEI SDK.
     * Requires a payment ID and payment token.
     * Optionally accepts customer, billing details, and shipping details.
     *
     * @param array $data Data for the confirm request containing paymentId and paymentToken
     *
     * @return Payment MONEI SDK Payment object
     * @throws LocalizedException If the payment cannot be confirmed
     */
    public function execute(array $data): Payment
    {
        $data = $this->convertKeysToSnakeCase($data);

        // Validate the request parameters
        $this->validateParams($data, $this->requiredArguments);

        // Create confirm request with SDK model
        $confirmRequest = new ConfirmPaymentRequest();

        // Set the payment token - required parameter
        $confirmRequest->setPaymentToken($data['payment_token']);

        // Add customer details if provided
        if (isset($data['customer']) && is_array($data['customer'])) {
            $customer = new PaymentCustomer($this->convertKeysToSnakeCase($data['customer']));
            $confirmRequest->setCustomer($customer);
        }

        // Add billing details if provided
        if (isset($data['billing_details']) && is_array($data['billing_details'])) {
            $billingDetails = new PaymentBillingDetails($this->convertKeysToSnakeCase($data['billing_details']));
            $confirmRequest->setBillingDetails($billingDetails);
        }

        // Add shipping details if provided
        if (isset($data['shipping_details']) && is_array($data['shipping_details'])) {
            $shippingDetails = new PaymentShippingDetails($this->convertKeysToSnakeCase($data['shipping_details']));
            $confirmRequest->setShippingDetails($shippingDetails);
        }

        // Add metadata if provided
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $confirmRequest->setMetadata($this->convertKeysToSnakeCase($data['metadata']));
        }

        // Use standardized SDK call pattern with the executeMoneiSdkCall method
        return $this->executeMoneiSdkCall(
            'confirmPayment',
            function (MoneiClient $moneiSdk) use ($data, $confirmRequest) {
                return $moneiSdk->payments->confirm($data['payment_id'], $confirmRequest);
            },
            $data
        );
    }
}

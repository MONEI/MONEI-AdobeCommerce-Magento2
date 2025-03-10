<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Monei\Model\Address;
use Monei\Model\CreatePaymentRequest;
use Monei\Model\Payment;
use Monei\Model\PaymentBillingDetails;
use Monei\Model\PaymentCustomer;
use Monei\Model\PaymentShippingDetails;
use Monei\Model\PaymentTransactionType;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiClient;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Monei create payment service class using the official MONEI PHP SDK.
 *
 * This class handles payment creation requests by building properly formatted
 * payment requests according to the MONEI SDK specifications and processing
 * responses from the API.
 */
class CreatePayment extends AbstractApiService implements CreatePaymentInterface
{
    /**
     * Required parameters for payment creation
     *
     * @var array
     */
    private array $requiredArguments = [
        'amount',
        'currency',
        'order_id',
        'shipping_details',
    ];

    /**
     * Module configuration provider
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * URL Builder
     *
     * @var Url
     */
    private Url $urlBuilder;

    /**
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient $apiClient API client factory for MONEI SDK
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration provider
     * @param Url $urlBuilder URL builder for creating frontend URLs
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Url $urlBuilder
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
        $this->moduleConfig = $moduleConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Execute a payment creation request to the Monei API.
     *
     * Creates a new payment using the official MONEI SDK.
     * The payment can be configured for authorization or capture based on the module configuration.
     *
     * @param array $data Payment data including amount, currency, order_id, customer and address details
     *
     * @return Payment Response from the API with payment creation results as Payment object
     * @throws LocalizedException If payment creation fails
     */
    public function execute(array $data): Payment
    {
        // Convert any camelCase keys to snake_case to ensure consistency
        $data = $this->convertKeysToSnakeCase($data);

        // Validate the request data
        $this->validateParams($data, $this->requiredArguments);

        // Build the payment request from the data
        $paymentRequest = $this->buildPaymentRequest($data);

        // Execute the SDK call with the standardized pattern
        $response = $this->executeMoneiSdkCall(
            'createPayment',
            function (MoneiClient $moneiSdk) use ($paymentRequest) {
                return $moneiSdk->payments->create($paymentRequest);
            },
            [
                'order_id' => $data['order_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency']
            ]
        );

        return $response;
    }

    /**
     * Build payment request object from provided data
     *
     * @param array $data Payment data
     * @return CreatePaymentRequest The constructed payment request
     */
    private function buildPaymentRequest(array $data): CreatePaymentRequest
    {
        // Create base payment request with required fields
        $paymentRequest = new CreatePaymentRequest([
            'amount' => $data['amount'],  // Already converted to cents
            'currency' => $data['currency'],
            'order_id' => $data['order_id'],
            'complete_url' => $this->urlBuilder->getUrl('monei/payment/complete'),
            'callback_url' => $this->urlBuilder->getUrl('monei/payment/callback'),
            'cancel_url' => $this->urlBuilder->getUrl('monei/payment/cancel'),
            'fail_url' => $this->urlBuilder->getUrl('monei/payment/fail')
        ]);

        // Set transaction type if necessary using the SDK enum
        if (TypeOfPayment::TYPE_PRE_AUTHORIZED === $this->moduleConfig->getTypeOfPayment()) {
            $paymentRequest->setTransactionType(PaymentTransactionType::AUTH);
        }

        // Set customer information if available
        if (isset($data['customer'])) {
            $customer = new PaymentCustomer($data['customer']);
            $paymentRequest->setCustomer($customer);
        }

        // Set billing details if available
        if (isset($data['billing_details']) && isset($data['billing_details']['address'])) {
            $billingAddress = new Address($data['billing_details']['address']);
            $billingDetails = new PaymentBillingDetails();
            $billingDetails->setAddress($billingAddress);
            $paymentRequest->setBillingDetails($billingDetails);
        }

        // Set shipping details if available
        if (isset($data['shipping_details']) && isset($data['shipping_details']['address'])) {
            $shippingAddress = new Address($data['shipping_details']['address']);
            $shippingDetails = new PaymentShippingDetails();
            $shippingDetails->setAddress($shippingAddress);
            $paymentRequest->setShippingDetails($shippingDetails);
        }

        // Set description if available
        if (isset($data['description'])) {
            $paymentRequest->setDescription($data['description']);
        }

        // Set metadata if available
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $paymentRequest->setMetadata($data['metadata']);
        }

        return $paymentRequest;
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use OpenAPI\Client\Model\Address;
use OpenAPI\Client\Model\CreatePaymentRequest;
use OpenAPI\Client\Model\PaymentBillingDetails;
use OpenAPI\Client\Model\PaymentCustomer;
use OpenAPI\Client\Model\PaymentShippingDetails;
use OpenAPI\Client\Model\PaymentTransactionType;

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
        'customer',
        'billing_details',
        'shipping_details',
    ];

    /**
     * Module configuration provider
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient $apiClient API client factory for MONEI SDK
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration provider
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Execute a payment creation request to the Monei API.
     *
     * Creates a new payment using the official MONEI SDK.
     * The payment can be configured for authorization or capture based on the module configuration.
     *
     * @param array $data Payment data including amount, currency, order_id, customer and address details
     *
     * @return array Response from the API with payment creation results
     * @throws LocalizedException If payment creation fails
     */
    public function execute(array $data): array
    {
        // Convert any camelCase keys to snake_case to ensure consistency
        $data = $this->convertKeysToSnakeCase($data);

        // Validate the request data
        $this->validateParams($data, $this->requiredArguments);

        // Build the payment request from the data
        $paymentRequest = $this->buildPaymentRequest($data);

        // Execute the SDK call with the standardized pattern
        return $this->executeMoneiSdkCall(
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
            // Add URLs from our configuration
            'complete_url' => $this->moduleConfig->getUrl() . '/monei/payment/complete',
            'callback_url' => $this->moduleConfig->getUrl() . '/monei/payment/callback',
            'cancel_url' => $this->moduleConfig->getUrl() . '/monei/payment/cancel',
            'fail_url' => $this->moduleConfig->getUrl() . '/monei/payment/fail'
        ]);

        // Set transaction type if necessary using the SDK enum
        if (TypeOfPayment::TYPE_PRE_AUTHORIZED === $this->moduleConfig->getTypeOfPayment()) {
            $paymentRequest->setTransactionType(PaymentTransactionType::AUTH);
        }

        // Set customer information if available
        if (isset($data['customer'])) {
            $customer = new PaymentCustomer();

            if (isset($data['customer']['email'])) {
                $customer->setEmail($data['customer']['email']);
            }

            if (isset($data['customer']['name'])) {
                $customer->setName($data['customer']['name']);
            }

            if (isset($data['customer']['phone'])) {
                $customer->setPhone($data['customer']['phone']);
            }

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

        // Add module version to metadata
        $metadata = $paymentRequest->getMetadata() ?: [];
        $metadata['magento_module'] = 'monei_magento2';
        $paymentRequest->setMetadata($metadata);

        return $paymentRequest;
    }
}

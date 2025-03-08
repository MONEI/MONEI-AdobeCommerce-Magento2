<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use OpenAPI\Client\Model\Address;
use OpenAPI\Client\Model\CreatePaymentRequest;
use OpenAPI\Client\Model\PaymentBillingDetails;
use OpenAPI\Client\Model\PaymentCustomer;
use OpenAPI\Client\Model\PaymentShippingDetails;
use OpenAPI\Client\Model\PaymentTransactionType;
use OpenAPI\Client\ApiException;

/**
 * Monei create payment service class using the official MONEI PHP SDK.
 */
class CreatePayment extends AbstractApiService implements CreatePaymentInterface
{
    /**
     * @var array
     */
    private $requiredArguments = [
        'amount',
        'currency',
        'order_id',
        'customer',
        'billing_details',
        'shipping_details',
    ];

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var MoneiApiClient
     */
    private $moneiApiClient;

    /**
     * @param Logger $logger
     * @param MoneiApiClient $moneiApiClient
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        Logger $logger,
        MoneiApiClient $moneiApiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        parent::__construct($logger);
        $this->moneiApiClient = $moneiApiClient;
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
     * @return array Response from the API with payment creation results or error details
     */
    public function execute(array $data): array
    {
        // Convert any camelCase keys to snake_case to ensure consistency
        $data = $this->convertKeysToSnakeCase($data);

        return $this->executeApiCall(__METHOD__, function () use ($data) {
            // Validate the request data
            $this->validateParams($data, $this->requiredArguments);

            try {
                // Create payment request object according to SDK
                $paymentRequest = new CreatePaymentRequest([
                    'amount' => $data['amount'],  // Convert to cents
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

                // Set customer information
                if (isset($data['customer'])) {
                    $paymentRequest->setCustomer(new PaymentCustomer([
                        'email' => $data['customer']['email'] ?? null,
                        'name' => $data['customer']['name'] ?? null,
                        'phone' => $data['customer']['phone'] ?? null
                    ]));
                }

                // Set billing details
                if (isset($data['billing_details']) && isset($data['billing_details']['address'])) {
                    $billingAddress = new Address($data['billing_details']['address']);
                    $paymentRequest->setBillingDetails(new PaymentBillingDetails([
                        'address' => $billingAddress
                    ]));
                }

                // Set shipping details
                if (isset($data['shipping_details']) && isset($data['shipping_details']['address'])) {
                    $shippingAddress = new Address($data['shipping_details']['address']);
                    $paymentRequest->setShippingDetails(new PaymentShippingDetails([
                        'address' => $shippingAddress
                    ]));
                }

                // Set description if available
                if (isset($data['description'])) {
                    $paymentRequest->setDescription($data['description']);
                }

                // Set metadata if available
                if (isset($data['metadata']) && is_array($data['metadata'])) {
                    $paymentRequest->setMetadata($data['metadata']);
                }

                // Get the SDK client
                $moneiSdk = $this->moneiApiClient->getMoneiSdk();

                // Create the payment using the request object
                $payment = $moneiSdk->payments->create($paymentRequest);

                // Convert response to array
                return $this->moneiApiClient->convertResponseToArray($payment);
            } catch (ApiException $e) {
                $this->logger->critical('[API Error] ' . $e->getMessage());

                throw new LocalizedException(__('Failed to create payment with MONEI API: %1', $e->getMessage()));
            }
        }, ['paymentData' => $data]);
    }
}

<?php

namespace Monei;

/**
 * Stub implementation of the MoneiClient for testing
 */
class MoneiClient
{
    /**
     * @var PaymentsApi
     */
    public $payments;

    /**
     * Constructor
     *
     * @param string $apiKey API key
     */
    public function __construct($apiKey)
    {
        $this->payments = new PaymentsApi();
    }
}

/**
 * Stub implementation of the PaymentsApi for testing
 */
class PaymentsApi
{
    /**
     * Create a payment
     *
     * @param Model\CreatePaymentRequest $request
     * @return Model\Payment
     */
    public function create($request)
    {
        // This is just a stub implementation - test mocks will override this
        return new Model\Payment();
    }

    /**
     * Get a payment by ID
     *
     * @param string $paymentId
     * @return Model\Payment
     */
    public function get($paymentId)
    {
        // This is just a stub implementation - test mocks will override this
        return new Model\Payment();
    }

    /**
     * Refund a payment
     *
     * @param string $paymentId
     * @param array $params
     * @return Model\Payment
     */
    public function refund($paymentId, $params = [])
    {
        // This is just a stub implementation - test mocks will override this
        return new Model\Payment();
    }
}

namespace Monei\Model;

/**
 * Stub implementation of the Payment model for testing
 */
class Payment
{
    public $id;
    public $status;
    public $amount;
    public $currency;
    public $metadata;

    // Add other properties as needed
}

/**
 * Stub implementation of the CreatePaymentRequest model for testing
 */
class CreatePaymentRequest
{
    private $amount;
    private $currency;
    private $orderId;
    private $allowedPaymentMethods;
    private $paymentToken;
    private $description;
    private $metadata;
    private $transactionType;
    private $customer;
    private $billingDetails;
    private $shippingDetails;
    private $successUrl;
    private $callbackUrl;
    private $cancelUrl;

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @return array
     */
    public function getAllowedPaymentMethods()
    {
        return $this->allowedPaymentMethods;
    }

    /**
     * @param array $allowedPaymentMethods
     * @return $this
     */
    public function setAllowedPaymentMethods($allowedPaymentMethods)
    {
        $this->allowedPaymentMethods = $allowedPaymentMethods;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentToken()
    {
        return $this->paymentToken;
    }

    /**
     * @param string $paymentToken
     * @return $this
     */
    public function setPaymentToken($paymentToken)
    {
        $this->paymentToken = $paymentToken;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     * @return $this
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionType()
    {
        return $this->transactionType;
    }

    /**
     * @param string $transactionType
     * @return $this
     */
    public function setTransactionType($transactionType)
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param array $customer
     * @return $this
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * @return array
     */
    public function getBillingDetails()
    {
        return $this->billingDetails;
    }

    /**
     * @param array $billingDetails
     * @return $this
     */
    public function setBillingDetails($billingDetails)
    {
        $this->billingDetails = $billingDetails;
        return $this;
    }

    /**
     * @return array
     */
    public function getShippingDetails()
    {
        return $this->shippingDetails;
    }

    /**
     * @param array $shippingDetails
     * @return $this
     */
    public function setShippingDetails($shippingDetails)
    {
        $this->shippingDetails = $shippingDetails;
        return $this;
    }

    /**
     * @return string
     */
    public function getSuccessUrl()
    {
        return $this->successUrl;
    }

    /**
     * @param string $successUrl
     * @return $this
     */
    public function setSuccessUrl($successUrl)
    {
        $this->successUrl = $successUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getCallbackUrl()
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     * @return $this
     */
    public function setCallbackUrl($callbackUrl)
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    /**
     * @param string $cancelUrl
     * @return $this
     */
    public function setCancelUrl($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;
        return $this;
    }
}

/**
 * Stub implementation of the PaymentTransactionType for testing
 */
class PaymentTransactionType
{
    const SALE = 'SALE';
    const AUTH = 'AUTH';
}

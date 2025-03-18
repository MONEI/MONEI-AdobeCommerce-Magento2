<?php

namespace Monei\MoneiPayment\Test\Unit\Stubs;

/**
 * Stub class for Monei SDK
 */
class MoneiClientStub
{
    public function __construct(string $apiKey = '') {}
    public function setUserAgent(string $agent) {}
}

// Define the Monei namespace if it doesn't exist
namespace Monei;

// Stub classes
if (!class_exists('\Monei\MoneiClient')) {
    class MoneiClient extends \Monei\MoneiPayment\Test\Unit\Stubs\MoneiClientStub {}
}

if (!class_exists('\Monei\PaymentsApi')) {
    class PaymentsApi
    {
        public function create($request) {}
    }
}

if (!class_exists('\Monei\Model\Payment')) {
    class Model
    {
        // Empty class to enable nested classes
    }

    class Payment
    {
        public function getId()
        {
            return 'pay_123456';
        }

        public function getStatus()
        {
            return 'SUCCEEDED';
        }

        public function getAmount()
        {
            return 1000;
        }

        public function getCurrency()
        {
            return 'EUR';
        }

        public function getOrderId()
        {
            return '000000123';
        }

        public function getCreatedAt()
        {
            return '2023-01-01T12:00:00Z';
        }

        public function getUpdatedAt()
        {
            return '2023-01-01T12:05:00Z';
        }

        public function getMetadata()
        {
            return null;
        }
    }
}

if (!class_exists('\Monei\Model\PaymentStatus')) {
    class PaymentStatus
    {
        const SUCCEEDED = 'SUCCEEDED';
        const AUTHORIZED = 'AUTHORIZED';
        const PENDING = 'PENDING';
        const FAILED = 'FAILED';
        const CANCELED = 'CANCELED';
        const EXPIRED = 'EXPIRED';
        const REFUNDED = 'REFUNDED';
        const PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    }
}

if (!class_exists('\Monei\Model\PaymentMethods')) {
    class PaymentMethods
    {
        const PAYMENT_METHODS_CARD = 'card';
        const PAYMENT_METHODS_BIZUM = 'bizum';
        const PAYMENT_METHODS_MBWAY = 'mbway';
        const PAYMENT_METHODS_MULTIBANCO = 'multibanco';
        const PAYMENT_METHODS_GOOGLE_PAY = 'googlepay';
        const PAYMENT_METHODS_APPLE_PAY = 'applepay';
        const PAYMENT_METHODS_PAYPAL = 'paypal';
    }
}

if (!class_exists('\Monei\Model\CreatePaymentRequest')) {
    class CreatePaymentRequest
    {
        private $amount;
        private $currency;
        private $orderId;
        private $completeUrl;
        private $callbackUrl;
        private $cancelUrl;
        private $allowedPaymentMethods;
        private $paymentToken;
        private $customer;
        private $billingDetails;
        private $shippingDetails;
        private $description;
        private $metadata;
        private $transactionType;

        public function __construct(array $params = [])
        {
            $this->amount = $params['amount'] ?? 0;
            $this->currency = $params['currency'] ?? 'EUR';
            $this->orderId = $params['order_id'] ?? '';
            $this->completeUrl = $params['complete_url'] ?? '';
            $this->callbackUrl = $params['callback_url'] ?? '';
            $this->cancelUrl = $params['cancel_url'] ?? '';
        }

        // Getters
        public function getAmount()
        {
            return $this->amount;
        }

        public function getCurrency()
        {
            return $this->currency;
        }

        public function getOrderId()
        {
            return $this->orderId;
        }

        public function getAllowedPaymentMethods()
        {
            return $this->allowedPaymentMethods;
        }

        public function getPaymentToken()
        {
            return $this->paymentToken;
        }

        public function getCustomer()
        {
            return $this->customer;
        }

        public function getBillingDetails()
        {
            return $this->billingDetails;
        }

        public function getShippingDetails()
        {
            return $this->shippingDetails;
        }

        public function getDescription()
        {
            return $this->description;
        }

        public function getMetadata()
        {
            return $this->metadata;
        }

        public function getTransactionType()
        {
            return $this->transactionType;
        }

        // Setters
        public function setAmount($amount)
        {
            $this->amount = $amount;

            return $this;
        }

        public function setCurrency($currency)
        {
            $this->currency = $currency;

            return $this;
        }

        public function setOrderId($orderId)
        {
            $this->orderId = $orderId;

            return $this;
        }

        public function setAllowedPaymentMethods($methods)
        {
            $this->allowedPaymentMethods = $methods;

            return $this;
        }

        public function setPaymentToken($token)
        {
            $this->paymentToken = $token;

            return $this;
        }

        public function setCustomer($customer)
        {
            $this->customer = $customer;

            return $this;
        }

        public function setBillingDetails($details)
        {
            $this->billingDetails = $details;

            return $this;
        }

        public function setShippingDetails($details)
        {
            $this->shippingDetails = $details;

            return $this;
        }

        public function setDescription($description)
        {
            $this->description = $description;

            return $this;
        }

        public function setMetadata($metadata)
        {
            $this->metadata = $metadata;

            return $this;
        }

        public function setTransactionType($type)
        {
            $this->transactionType = $type;

            return $this;
        }
    }
}

if (!class_exists('\Monei\Model\PaymentTransactionType')) {
    class PaymentTransactionType
    {
        const AUTH = 'AUTH';
        const SALE = 'SALE';
    }
}

if (!class_exists('\Monei\Model\Address')) {
    class Address
    {
        private $city;
        private $country;
        private $line1;
        private $line2;
        private $postalCode;
        private $state;

        public function __construct(array $address = [])
        {
            $this->city = $address['city'] ?? '';
            $this->country = $address['country'] ?? '';
            $this->line1 = $address['line1'] ?? '';
            $this->line2 = $address['line2'] ?? '';
            $this->postalCode = $address['postal_code'] ?? '';
            $this->state = $address['state'] ?? '';
        }

        // Getters
        public function getCity()
        {
            return $this->city;
        }

        public function getCountry()
        {
            return $this->country;
        }

        public function getLine1()
        {
            return $this->line1;
        }

        public function getLine2()
        {
            return $this->line2;
        }

        public function getPostalCode()
        {
            return $this->postalCode;
        }

        public function getState()
        {
            return $this->state;
        }
    }
}

if (!class_exists('\Monei\Model\PaymentCustomer')) {
    class PaymentCustomer
    {
        private $email;
        private $name;
        private $phone;

        public function __construct(array $customer = [])
        {
            $this->email = $customer['email'] ?? '';
            $this->name = $customer['name'] ?? '';
            $this->phone = $customer['phone'] ?? '';
        }

        // Getters
        public function getEmail()
        {
            return $this->email;
        }

        public function getName()
        {
            return $this->name;
        }

        public function getPhone()
        {
            return $this->phone;
        }
    }
}

if (!class_exists('\Monei\Model\PaymentBillingDetails')) {
    class PaymentBillingDetails
    {
        private $address;

        public function setAddress($address)
        {
            $this->address = $address;

            return $this;
        }

        public function getAddress()
        {
            return $this->address;
        }
    }
}

if (!class_exists('\Monei\Model\PaymentShippingDetails')) {
    class PaymentShippingDetails
    {
        private $address;

        public function setAddress($address)
        {
            $this->address = $address;

            return $this;
        }

        public function getAddress()
        {
            return $this->address;
        }
    }
}

if (!class_exists('\Monei\ApiException')) {
    class ApiException extends \Exception
    {
        private $responseBody;

        public function getResponseBody()
        {
            return $this->responseBody;
        }

        public function setResponseBody($body)
        {
            $this->responseBody = $body;

            return $this;
        }
    }
}

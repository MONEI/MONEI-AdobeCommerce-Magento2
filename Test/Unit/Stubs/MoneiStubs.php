<?php

namespace Monei;

// MoneiClient class
if (!class_exists('\Monei\MoneiClient')) {
    class MoneiClient
    {
        public $payments;
        public $applePayDomain;

        public function __construct(string $apiKey = '')
        {
            $this->payments = new PaymentsApi();
            $this->applePayDomain = new ApplePayDomainApi();
        }

        public function setUserAgent(string $agent)
        {
            // Stub implementation
        }
    }
}

// PaymentsApi class
if (!class_exists('\Monei\PaymentsApi')) {
    class PaymentsApi
    {
        public function create($request)
        {
            $payment = new Model\Payment();

            return $payment;
        }

        public function get($paymentId)
        {
            $payment = new Model\Payment();

            return $payment;
        }

        public function refund($paymentId, $refundRequest)
        {
            $payment = new Model\Payment();

            return $payment;
        }
    }
}

// ApiException class
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

// ApplePayDomainApi class
if (!class_exists('\Monei\ApplePayDomainApi')) {
    class ApplePayDomainApi
    {
        public function register($request)
        {
            $response = new \Monei\Model\ApplePayDomainRegister200Response();
            return $response;
        }
    }
}

// Define Model classes in separate namespace
namespace Monei\Model;

// Payment class
if (!class_exists('\Monei\Model\Payment')) {
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

// PaymentStatus class
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

// PaymentMethods class
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

// PaymentTransactionType class
if (!class_exists('\Monei\Model\PaymentTransactionType')) {
    class PaymentTransactionType
    {
        const AUTH = 'AUTH';
        const SALE = 'SALE';
    }
}

// CreatePaymentRequest class
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

// Address class
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

// PaymentCustomer class
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

// PaymentBillingDetails class
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

// PaymentShippingDetails class
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

namespace Monei\MoneiPayment\Model\Config\Source;

// Add missing TYPE constants
if (!class_exists('\Monei\MoneiPayment\Model\Config\Source\TypeOfPayment')) {
    class TypeOfPayment implements \Magento\Framework\Data\OptionSourceInterface
    {
        public const TYPE_PRE_AUTHORIZED = 1;
        public const TYPE_AUTHORIZED = 2;

        public function toOptionArray(): array
        {
            return [
                ['label' => 'Authorize', 'value' => self::TYPE_PRE_AUTHORIZED],
                ['label' => 'Authorize and Capture', 'value' => self::TYPE_AUTHORIZED],
            ];
        }
    }
}

// RegisterApplePayDomainRequest class
if (!class_exists('\Monei\Model\RegisterApplePayDomainRequest')) {
    class RegisterApplePayDomainRequest
    {
        private $domainName;

        public function __construct(array $params = [])
        {
            $this->domainName = $params['domain_name'] ?? '';
        }

        public function getDomainName()
        {
            return $this->domainName;
        }

        public function setDomainName($domainName)
        {
            $this->domainName = $domainName;
            return $this;
        }
    }
}

// ApplePayDomainRegister200Response class
if (!class_exists('\Monei\Model\ApplePayDomainRegister200Response')) {
    class ApplePayDomainRegister200Response
    {
        private $domainName;
        private $status = 'verified';

        public function getDomainName()
        {
            return $this->domainName;
        }

        public function getStatus()
        {
            return $this->status;
        }

        public function setDomainName($domainName)
        {
            $this->domainName = $domainName;
            return $this;
        }

        public function setStatus($status)
        {
            $this->status = $status;
            return $this;
        }
    }
}

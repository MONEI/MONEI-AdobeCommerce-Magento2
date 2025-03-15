<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\PaymentMethods;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\Service\StatusCodeHandler;

/**
 * Data Transfer Object for MONEI payment data
 */
class PaymentDTO
{
    /**
     * @var StatusCodeHandler
     */
    private StatusCodeHandler $statusCodeHandler;

    /**
     * @var string
     */
    private string $id;

    /**
     * @var string
     */
    private string $status;

    /**
     * @var float Amount in currency units (e.g., euros, dollars)
     */
    private float $amount;

    /**
     * @var int Original amount in cents from API
     */
    private int $amountInCents;

    /**
     * @var string
     */
    private string $currency;

    /**
     * @var string
     */
    private string $orderId;

    /**
     * @var string|null
     */
    private ?string $createdAt;

    /**
     * @var string|null
     */
    private ?string $updatedAt;

    /**
     * @var array|null Metadata from payment (converted to array if received as object)
     */
    private ?array $metadata;

    /**
     * @var array
     */
    private array $rawData;

    /**
     * @var string|null Status code when available (e.g. E201)
     */
    private ?string $statusCode = null;

    /**
     * @var string|null Status message when available
     */
    private ?string $statusMessage = null;

    /**
     * PaymentDTO constructor.
     *
     * @param StatusCodeHandler $statusCodeHandler
     * @param string $id
     * @param string $status
     * @param int $amountInCents Amount in smallest currency unit (cents)
     * @param string $currency
     * @param string $orderId
     * @param string|int|null $createdAt Timestamp as string or integer
     * @param string|int|null $updatedAt Timestamp as string or integer
     * @param array|object|null $metadata Metadata can be array or object (converted to array)
     * @param array $rawData
     */
    public function __construct(
        StatusCodeHandler $statusCodeHandler,
        string $id,
        string $status,
        int $amountInCents,
        string $currency,
        string $orderId,
        $createdAt = null,
        $updatedAt = null,
        $metadata = null,
        array $rawData = []
    ) {
        $this->statusCodeHandler = $statusCodeHandler;
        $this->id = $id;
        $this->status = $status;
        $this->amountInCents = $amountInCents;
        // Convert cents to standard currency units (divide by 100)
        $this->amount = $amountInCents / 100.0;
        $this->currency = $currency;
        $this->orderId = $orderId;

        // Convert timestamps to strings if they are integers
        $this->createdAt = is_int($createdAt) ? (string) $createdAt : $createdAt;
        $this->updatedAt = is_int($updatedAt) ? (string) $updatedAt : $updatedAt;

        // Convert metadata object to array if needed
        if (is_object($metadata)) {
            $this->metadata = (array) $metadata;
        } else {
            $this->metadata = $metadata;
        }

        $this->rawData = $rawData;

        // Extract status code from raw data using the status code handler service
        $this->statusCode = $this->statusCodeHandler->extractStatusCodeFromData($rawData);
    }

    /**
     * Create a PaymentDTO from array data
     *
     * @param StatusCodeHandler $statusCodeHandler
     * @param array $data
     * @return self
     * @throws LocalizedException
     */
    public static function fromArray(StatusCodeHandler $statusCodeHandler, array $data): self
    {
        // Handle case when we get the data inside a 'response' key from the API
        if (isset($data['response']) && is_array($data['response'])) {
            $data = $data['response'];
        }

        // Validate required fields
        $requiredFields = ['id', 'status', 'amount', 'currency', 'orderId'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && empty($data[$field]))) {
                throw new LocalizedException(__('Missing required field: %1', $field));
            }
        }

        // Convert timestamps to strings if they are integers
        $createdAt = isset($data['createdAt']) ? (is_int($data['createdAt']) ? (string) $data['createdAt'] : $data['createdAt']) : null;
        $updatedAt = isset($data['updatedAt']) ? (is_int($data['updatedAt']) ? (string) $data['updatedAt'] : $data['updatedAt']) : null;

        // Ensure amount is treated as an integer
        $amountInCents = (int) $data['amount'];

        // Handle metadata - convert stdClass to array if needed
        $metadata = null;
        if (isset($data['metadata'])) {
            if (is_array($data['metadata'])) {
                $metadata = $data['metadata'];
            } elseif (is_object($data['metadata'])) {
                $metadata = (array) $data['metadata'];
            }
        }

        return new self(
            $statusCodeHandler,
            $data['id'],
            $data['status'],
            $amountInCents,
            $data['currency'],
            $data['orderId'],
            $createdAt,
            $updatedAt,
            $metadata,
            $data
        );
    }

    /**
     * Create a PaymentDTO from MONEI SDK Payment object
     *
     * @param StatusCodeHandler $statusCodeHandler
     * @param \Monei\Model\Payment $payment
     * @return self
     * @throws LocalizedException
     */
    public static function fromPaymentObject(StatusCodeHandler $statusCodeHandler, \Monei\Model\Payment $payment): self
    {
        // Extract data using getters rather than relying on object serialization
        try {
            $data = [
                'id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'orderId' => $payment->getOrderId(),
                'createdAt' => $payment->getCreatedAt(),
                'updatedAt' => $payment->getUpdatedAt()
            ];

            // Add metadata if available
            if ($payment->getMetadata()) {
                $data['metadata'] = $payment->getMetadata();
            }

            // Add status code if available - safely check if the method exists
            if (method_exists($payment, 'getStatusCode')) {
                try {
                    $statusCode = $payment->getStatusCode();
                    if ($statusCode) {
                        $data['statusCode'] = $statusCode;
                    }
                } catch (\Exception $e) {
                    // Silently handle the case when the method exists but fails
                }
            } elseif (isset($payment->statusCode)) {
                // Try to access the property directly if it exists
                $data['statusCode'] = $payment->statusCode;
            }

            // Store the original payment object for reference
            $data['original_payment'] = $payment;

            return self::fromArray($statusCodeHandler, $data);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Failed to convert Payment object to DTO: %1', $e->getMessage()));
        }
    }

    /**
     * Get payment ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get payment status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get payment amount in currency units (e.g., euros, dollars)
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Get payment amount in cents/smallest currency unit (as received from API)
     *
     * @return int
     */
    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    /**
     * Get payment currency
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get order ID
     *
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * Get created at timestamp
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * Get metadata
     *
     * @return array|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Get raw data
     *
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Get status code if available
     *
     * @return string|null
     */
    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    /**
     * Get status message if available
     *
     * @return string|null
     */
    public function getStatusMessage(): ?string
    {
        // If we have a direct status message, return it
        if ($this->statusMessage !== null) {
            return $this->statusMessage;
        }

        // If we have a status code but no message, get the message from the handler
        if ($this->statusCode !== null) {
            return $this->statusCodeHandler->getStatusMessage($this->statusCode)->__toString();
        }

        // No status code or message available
        return null;
    }

    /**
     * Check if the payment was successful based on status code
     *
     * @return bool
     */
    public function hasSuccessfulStatusCode(): bool
    {
        return $this->statusCodeHandler->isSuccessCode($this->statusCode);
    }

    /**
     * Check if payment has an error status
     *
     * @return bool
     */
    public function hasErrorStatus(): bool
    {
        return $this->statusCodeHandler->isErrorCode($this->statusCode);
    }

    /**
     * Check if payment is succeeded
     *
     * @return bool
     */
    public function isSucceeded(): bool
    {
        return strtoupper($this->status) === PaymentStatus::SUCCEEDED;
    }

    /**
     * Check if payment is authorized
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return strtoupper($this->status) === PaymentStatus::AUTHORIZED;
    }

    /**
     * Check if payment is failed
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return strtoupper($this->status) === PaymentStatus::FAILED;
    }

    /**
     * Check if payment is canceled
     *
     * @return bool
     */
    public function isCanceled(): bool
    {
        return strtoupper($this->status) === PaymentStatus::CANCELED;
    }

    /**
     * Check if payment is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return strtoupper($this->status) === PaymentStatus::EXPIRED;
    }

    /**
     * Check if payment is refunded
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return strtoupper($this->status) === PaymentStatus::REFUNDED;
    }

    /**
     * Check if payment is partially refunded
     *
     * @return bool
     */
    public function isPartiallyRefunded(): bool
    {
        return strtoupper($this->status) === PaymentStatus::PARTIALLY_REFUNDED;
    }

    /**
     * Check if payment is pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return strtoupper($this->status) === PaymentStatus::PENDING;
    }

    /**
     * Get the payment method type
     *
     * @return string|null
     */
    public function getPaymentMethodType(): ?string
    {
        if (
            isset($this->rawData['original_payment']) &&
            method_exists($this->rawData['original_payment'], 'getPaymentMethod')
        ) {
            $paymentMethod = $this->rawData['original_payment']->getPaymentMethod();
            if (isset($paymentMethod->type)) {
                return $paymentMethod->type;
            }
        }

        return null;
    }

    /**
     * Check if payment method is MBWAY
     *
     * @return bool
     */
    public function isMbway(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_MBWAY;
    }

    /**
     * Check if payment method is Card
     *
     * @return bool
     */
    public function isCard(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_CARD;
    }

    /**
     * Check if payment method is Bizum
     *
     * @return bool
     */
    public function isBizum(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_BIZUM;
    }

    /**
     * Check if payment method is Multibanco
     *
     * @return bool
     */
    public function isMultibanco(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_MULTIBANCO;
    }

    /**
     * Check if payment method is Google Pay
     *
     * @return bool
     */
    public function isGooglePay(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_GOOGLE_PAY;
    }

    /**
     * Check if payment method is Apple Pay
     *
     * @return bool
     */
    public function isApplePay(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_APPLE_PAY;
    }

    /**
     * Check if payment method is PayPal
     *
     * @return bool
     */
    public function isPaypal(): bool
    {
        $paymentMethodType = $this->getPaymentMethodType();

        return $paymentMethodType === PaymentMethods::PAYMENT_METHODS_PAYPAL;
    }

    /**
     * Set payment ID
     *
     * @param string $id
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set payment status
     *
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Set payment amount in currency units (e.g., euros, dollars)
     *
     * @param float $amount
     * @return self
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Set payment amount in cents/smallest currency unit (as received from API)
     *
     * @param int $amountInCents
     * @return self
     */
    public function setAmountInCents(int $amountInCents): self
    {
        $this->amountInCents = $amountInCents;

        return $this;
    }

    /**
     * Set payment currency
     *
     * @param string $currency
     * @return self
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Set order ID
     *
     * @param string $orderId
     * @return self
     */
    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * Set created at timestamp
     *
     * @param string|null $createdAt
     * @return self
     */
    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Set updated at timestamp
     *
     * @param string|null $updatedAt
     * @return self
     */
    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Set metadata
     *
     * @param array|null $metadata
     * @return self
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set raw data
     *
     * @param array $rawData
     * @return self
     */
    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Set status code
     *
     * @param string|null $statusCode
     * @return self
     */
    public function setStatusCode(?string $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Set status message
     *
     * @param string|null $statusMessage
     * @return self
     */
    public function setStatusMessage(?string $statusMessage): self
    {
        $this->statusMessage = $statusMessage;

        return $this;
    }

    /**
     * Update from array (used for API data)
     *
     * @param array $data
     * @return self
     */
    public function updateFromArray(array $data): self
    {
        $this->setId($data['id'] ?? '');
        $this->setOrderId($data['orderId'] ?? '');
        $this->setStatus($data['status'] ?? '');
        $this->setAmount($data['amount'] ?? 0);
        $this->setCurrency($data['currency'] ?? '');
        $this->setRawData($data);

        // Extract status code and message
        $statusCode = $this->statusCodeHandler->extractStatusCodeFromData($data);
        $statusMessage = $this->statusCodeHandler->extractStatusMessageFromData($data);

        // Set status code and message
        $this->setStatusCode($statusCode);
        $this->setStatusMessage($statusMessage);

        return $this;
    }
}

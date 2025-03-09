<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Model\Payment\Status;

/**
 * Data Transfer Object for MONEI payment data
 */
class PaymentDTO
{
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
     * PaymentDTO constructor.
     *
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
        $this->id = $id;
        $this->status = $status;
        $this->amountInCents = $amountInCents;
        // Convert cents to standard currency units (divide by 100)
        $this->amount = $amountInCents / 100.0;
        $this->currency = $currency;
        $this->orderId = $orderId;

        // Convert timestamps to strings if they are integers
        $this->createdAt = is_int($createdAt) ? (string)$createdAt : $createdAt;
        $this->updatedAt = is_int($updatedAt) ? (string)$updatedAt : $updatedAt;

        // Convert metadata object to array if needed
        if (is_object($metadata)) {
            $this->metadata = (array)$metadata;
        } else {
            $this->metadata = $metadata;
        }

        $this->rawData = $rawData;
    }

    /**
     * Create a PaymentDTO from array data
     *
     * @param array $data
     * @return self
     * @throws LocalizedException
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        $requiredFields = ['id', 'status', 'amount', 'currency', 'orderId'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && empty($data[$field]))) {
                throw new LocalizedException(__('Missing required field: %1', $field));
            }
        }

        // Convert timestamps to strings if they are integers
        $createdAt = isset($data['createdAt']) ? (is_int($data['createdAt']) ? (string)$data['createdAt'] : $data['createdAt']) : null;
        $updatedAt = isset($data['updatedAt']) ? (is_int($data['updatedAt']) ? (string)$data['updatedAt'] : $data['updatedAt']) : null;

        // Ensure amount is treated as an integer
        $amountInCents = (int) $data['amount'];

        // Handle metadata - convert stdClass to array if needed
        $metadata = null;
        if (isset($data['metadata'])) {
            if (is_array($data['metadata'])) {
                $metadata = $data['metadata'];
            } elseif (is_object($data['metadata'])) {
                $metadata = (array)$data['metadata'];
            }
        }

        return new self(
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
}

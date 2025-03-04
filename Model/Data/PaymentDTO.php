<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

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
     * @var float
     */
    private float $amount;

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
     * @var array|null
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
     * @param float $amount
     * @param string $currency
     * @param string $orderId
     * @param string|null $createdAt
     * @param string|null $updatedAt
     * @param array|null $metadata
     * @param array $rawData
     */
    public function __construct(
        string $id,
        string $status,
        float $amount,
        string $currency,
        string $orderId,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?array $metadata = null,
        array $rawData = []
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->orderId = $orderId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->metadata = $metadata;
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
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new LocalizedException(__('Missing required field: %1', $field));
            }
        }

        return new self(
            $data['id'],
            $data['status'],
            (float) $data['amount'],
            $data['currency'],
            $data['orderId'],
            $data['createdAt'] ?? null,
            $data['updatedAt'] ?? null,
            $data['metadata'] ?? null,
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
     * Get payment amount
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
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
        return strtoupper($this->status) === 'SUCCEEDED';
    }

    /**
     * Check if payment is authorized
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return strtoupper($this->status) === 'AUTHORIZED';
    }

    /**
     * Check if payment is failed
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return strtoupper($this->status) === 'FAILED';
    }

    /**
     * Check if payment is canceled
     *
     * @return bool
     */
    public function isCanceled(): bool
    {
        return strtoupper($this->status) === 'CANCELED';
    }

    /**
     * Check if payment is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return strtoupper($this->status) === 'EXPIRED';
    }

    /**
     * Check if payment is refunded
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return strtoupper($this->status) === 'REFUNDED';
    }

    /**
     * Check if payment is partially refunded
     *
     * @return bool
     */
    public function isPartiallyRefunded(): bool
    {
        return strtoupper($this->status) === 'PARTIALLY_REFUNDED';
    }
}

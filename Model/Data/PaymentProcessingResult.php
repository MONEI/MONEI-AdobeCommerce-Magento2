<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Data;

use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;

/**
 * Payment processing result implementation
 */
class PaymentProcessingResult implements PaymentProcessingResultInterface
{
    /**
     * @var string
     */
    private string $status;

    /**
     * @var string
     */
    private string $orderId;

    /**
     * @var string
     */
    private string $paymentId;

    /**
     * @var bool
     */
    private bool $successful;

    /**
     * @var string|null
     */
    private ?string $errorMessage = null;

    /**
     * @var string|null
     */
    private ?string $statusCode = null;

    /**
     * @var array|null
     */
    private ?array $fullErrorResponse = null;

    /**
     * @param string $status
     * @param string $orderId
     * @param string $paymentId
     * @param bool $successful
     * @param string|null $errorMessage
     * @param string|null $statusCode
     * @param array|null $fullErrorResponse
     */
    public function __construct(
        string $status,
        string $orderId,
        string $paymentId,
        bool $successful,
        ?string $errorMessage = null,
        ?string $statusCode = null,
        ?array $fullErrorResponse = null
    ) {
        $this->status = $status;
        $this->orderId = $orderId;
        $this->paymentId = $paymentId;
        $this->successful = $successful;
        $this->errorMessage = $errorMessage;
        $this->statusCode = $statusCode;
        $this->fullErrorResponse = $fullErrorResponse;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * @inheritdoc
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @inheritdoc
     */
    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    /**
     * @inheritdoc
     */
    public function getFullErrorResponse(): ?array
    {
        return $this->fullErrorResponse;
    }

    /**
     * @inheritdoc
     */
    public function getDisplayErrorMessage(): ?string
    {
        if (!$this->errorMessage) {
            return null;
        }

        // Format the error message for display
        return __('MONEI Payment Error: %1', $this->errorMessage)->render();
    }

    /**
     * Create a successful result
     *
     * @param string $status
     * @param string $orderId
     * @param string $paymentId
     * @return self
     */
    public static function createSuccess(string $status, string $orderId, string $paymentId): self
    {
        return new self($status, $orderId, $paymentId, true);
    }

    /**
     * Create an error result
     *
     * @param string $status
     * @param string $orderId
     * @param string $paymentId
     * @param string $errorMessage
     * @param string|null $statusCode
     * @param array|null $fullErrorResponse
     * @return self
     */
    public static function createError(
        string $status,
        string $orderId,
        string $paymentId,
        string $errorMessage,
        ?string $statusCode = null,
        ?array $fullErrorResponse = null
    ): self {
        return new self(
            $status,
            $orderId,
            $paymentId,
            false,
            $errorMessage,
            $statusCode,
            $fullErrorResponse
        );
    }
}

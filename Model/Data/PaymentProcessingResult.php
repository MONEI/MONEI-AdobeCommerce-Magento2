<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Data;

use Monei\MoneiPayment\Api\Data\PaymentErrorCodeInterface;
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
     * Alias for isSuccessful() to maintain backward compatibility
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->isSuccessful();
    }

    /**
     * Get the message (alias for getErrorMessage or a success message)
     *
     * @return string
     */
    public function getMessage(): string
    {
        if ($this->isSuccessful()) {
            return 'Payment processed successfully.';
        }

        return $this->getErrorMessage() ?? 'Unknown error occurred.';
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
        // Use ERROR_UNKNOWN if no status code is provided
        if ($statusCode === null) {
            $statusCode = PaymentErrorCodeInterface::ERROR_UNKNOWN;
        }

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

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Model\Service\StatusCodeHandler;

/**
 * Factory for creating PaymentDTO objects
 */
class PaymentDTOFactory
{
    /**
     * @var StatusCodeHandler
     */
    private StatusCodeHandler $statusCodeHandler;

    /**
     * PaymentDTOFactory constructor
     *
     * @param StatusCodeHandler $statusCodeHandler
     */
    public function __construct(
        StatusCodeHandler $statusCodeHandler
    ) {
        $this->statusCodeHandler = $statusCodeHandler;
    }

    /**
     * Create a PaymentDTO from array data
     *
     * @param array $data
     * @return PaymentDTO
     * @throws LocalizedException
     */
    public function createFromArray(array $data): PaymentDTO
    {
        return PaymentDTO::fromArray($this->statusCodeHandler, $data);
    }

    /**
     * Create a PaymentDTO from MONEI SDK Payment object
     *
     * @param \Monei\Model\Payment $payment
     * @return PaymentDTO
     * @throws LocalizedException
     */
    public function createFromPaymentObject(\Monei\Model\Payment $payment): PaymentDTO
    {
        return PaymentDTO::fromPaymentObject($this->statusCodeHandler, $payment);
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Model\Data\PaymentDTO;

/**
 * Interface for payment data providers
 */
interface PaymentDataProviderInterface
{
    /**
     * Get payment data for a specific payment ID
     *
     * @param string $paymentId
     * @return PaymentDTO
     * @throws LocalizedException
     */
    public function getPaymentData(string $paymentId): PaymentDTO;

    /**
     * Validate payment data
     *
     * @param array $data
     * @return bool
     * @throws LocalizedException
     */
    public function validatePaymentData(array $data): bool;
}

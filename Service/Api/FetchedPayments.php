<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Api;

use Monei\Model\Payment;

/**
 * The payments that CallbackVerifier fetched in this request. GetPayment answers from it, so processing a callback
 * (the order email, the vault token) does not fetch the payment again.
 */
class FetchedPayments
{
    /**
     * @var array<string, Payment>
     */
    private array $payments = [];

    /**
     * Keep a fetched payment for the rest of the request
     *
     * @param Payment $payment
     * @return void
     */
    public function add(Payment $payment): void
    {
        $this->payments[(string) $payment->getId()] = $payment;
    }

    /**
     * Get a payment fetched in this request
     *
     * @param string $paymentId
     * @return Payment|null
     */
    public function get(string $paymentId): ?Payment
    {
        return $this->payments[$paymentId] ?? null;
    }
}

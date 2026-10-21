<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

/**
 * Checks that a MONEI payment covers an order's base grand total in its base currency
 */
class PaymentAmountMatcher
{
    /**
     * Check the payment amount and currency against the order total
     *
     * Payments are created in cents both with round() and with an (int) cast that
     * truncates (19.99 * 100 gives 1998), so either conversion is a match.
     *
     * @param int $paymentAmountInCents
     * @param string $paymentCurrency
     * @param float $orderBaseGrandTotal
     * @param string $orderBaseCurrency
     * @return bool
     */
    public function matches(
        int $paymentAmountInCents,
        string $paymentCurrency,
        float $orderBaseGrandTotal,
        string $orderBaseCurrency
    ): bool {
        if ($paymentCurrency !== $orderBaseCurrency) {
            return false;
        }

        $orderTotalInCents = $orderBaseGrandTotal * 100;

        return $paymentAmountInCents === (int) round($orderTotalInCents)
            || $paymentAmountInCents === (int) $orderTotalInCents;
    }
}

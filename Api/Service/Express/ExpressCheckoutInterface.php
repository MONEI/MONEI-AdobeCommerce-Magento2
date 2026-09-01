<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Express;

/**
 * Express checkout server-side operations.
 *
 * The client never sends an amount. Every figure the shopper is shown, and the
 * amount finally charged, is computed here from the quote.
 *
 * The quote comes from the session rather than a request parameter. These routes
 * are anonymous - express runs before a shopper identifies themselves - so taking
 * a cart id from the caller would let anyone address someone else's cart.
 */
interface ExpressCheckoutInterface
{
    /**
     * Shipping options for an address the shopper picked in the wallet sheet.
     *
     * Returns the options plus the recalculated total with the first option
     * already applied, because the wallet auto-selects it.
     *
     * @param mixed[] $address Partial address from the wallet
     *
     * @return mixed[]
     */
    public function getShippingOptions(array $address): array;

    /**
     * Apply the shipping option the shopper chose and return the new total.
     *
     * @param mixed[] $address
     * @param string  $optionId
     *
     * @return mixed[]
     */
    public function selectShippingOption(array $address, string $optionId): array;

    /**
     * Place the order for an approved wallet payment.
     *
     * @param mixed[] $payload The wallet SubmitResult plus the originating surface
     *
     * @return mixed[]
     */
    public function placeOrder(array $payload): array;
}

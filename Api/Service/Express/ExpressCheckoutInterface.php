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
 *
 * Payloads arrive JSON-encoded. Magento's webapi TypeProcessor resolves an untyped
 * array parameter to anyType and then calls settype() with it, which throws, so a
 * nested wallet payload cannot be declared as a plain array. Results are returned
 * the same way: an untyped array return is serialised positionally, losing the keys.
 */
interface ExpressCheckoutInterface
{
    /**
     * Shipping options for an address the shopper picked in the wallet sheet.
     *
     * Returns the options plus the recalculated total with the first option
     * already applied, because the wallet auto-selects it.
     *
     * @param string $address JSON-encoded partial address from the wallet
     *
     * @return string JSON-encoded result
     */
    public function getShippingOptions(string $address): string;

    /**
     * Apply the shipping option the shopper chose and return the new total.
     *
     * @param string $address  JSON-encoded partial address from the wallet
     * @param string $optionId
     *
     * @return string JSON-encoded result
     */
    public function selectShippingOption(string $address, string $optionId): string;

    /**
     * Place the order for an approved wallet payment.
     *
     * @param string $payload JSON-encoded wallet SubmitResult plus the originating surface
     *
     * @return string JSON-encoded result
     */
    public function placeOrder(string $payload): string;
}

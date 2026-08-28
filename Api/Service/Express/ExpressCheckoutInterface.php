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
 */
interface ExpressCheckoutInterface
{
    /**
     * Shipping options for an address the shopper picked in the wallet sheet.
     *
     * Returns the options plus the recalculated total with the first option
     * already applied, because the wallet auto-selects it.
     *
     * @param string  $cartId
     * @param mixed[] $address Partial address from the wallet
     *
     * @return mixed[]
     */
    public function getShippingOptions(string $cartId, array $address): array;

    /**
     * Apply the shipping option the shopper chose and return the new total.
     *
     * @param string  $cartId
     * @param mixed[] $address
     * @param string  $optionId
     *
     * @return mixed[]
     */
    public function selectShippingOption(string $cartId, array $address, string $optionId): array;

    /**
     * Place the order for an approved wallet payment.
     *
     * @param string  $cartId
     * @param mixed[] $payload The wallet SubmitResult plus the originating surface
     *
     * @return mixed[]
     */
    public function placeOrder(string $cartId, array $payload): array;
}

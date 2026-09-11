<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Config;

/**
 * Express checkout configuration interface.
 */
interface MoneiExpressCheckoutConfigInterface
{
    public const IS_ENABLED = 'payment/monei_express/active';

    public const ENABLED_ON_PRODUCT = 'payment/monei_express/enabled_on_product';

    public const ENABLED_ON_CART = 'payment/monei_express/enabled_on_cart';

    public const ENABLED_ON_MINICART = 'payment/monei_express/enabled_on_minicart';

    public const JSON_STYLE = 'payment/monei_express/json_style';

    public const PAYPAL_ENABLED = 'payment/monei_express/paypal_enabled';

    /**
     * Surface identifiers, matching the values passed from the frontend.
     */
    public const LOCATION_PRODUCT = 'product';

    public const LOCATION_CART = 'cart';

    public const LOCATION_MINICART = 'minicart';

    /**
     * Whether express checkout is enabled at all.
     *
     * @param ?int $storeId
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Whether express checkout is enabled on a given surface.
     *
     * Returns false when express is globally disabled, whatever the per-surface
     * setting says.
     *
     * @param string $location One of the LOCATION_* constants
     * @param ?int   $storeId
     */
    public function isEnabledAt(string $location, ?int $storeId = null): bool;

    /**
     * Whether a PayPal button joins the wallet button in the express block.
     *
     * Requires express itself and the PayPal payment method to be enabled: the
     * order it places is a PayPal order.
     *
     * @param ?int $storeId
     */
    public function isPayPalEnabled(?int $storeId = null): bool;

    /**
     * Style object passed to the wallet button.
     *
     * @param ?int $storeId
     */
    public function getJsonStyle(?int $storeId = null): array;
}

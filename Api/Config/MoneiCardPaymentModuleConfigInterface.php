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
 * Get payment method configuration interface.
 */
interface MoneiCardPaymentModuleConfigInterface
{
    public const IS_PAYMENT_ENABLED = 'payment/monei_card/active';

    public const TITLE = 'payment/monei_card/title';

    public const IS_ENABLED_TOKENIZATION = 'payment/monei_card/is_enabled_tokenization';

    public const ALLOW_SPECIFIC = 'payment/monei_card/allowspecific';

    public const SPECIFIC_COUNTRIES = 'payment/monei_card/specificcountry';

    public const SORT_ORDER = 'payment/monei_card/sort_order';

    public const JSON_STYLE = 'payment/monei_card/json_style';

    public const CARD_INPUT_LAYOUT = 'payment/monei_card/card_input_layout';

    public const LAYOUT_SPLIT = 'split';

    public const LAYOUT_SINGLE = 'single';

    /**
     * Check if payment method is enabled.
     *
     * @param ?int $storeId
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Get payment method title.
     *
     * @param ?int $storeId
     */
    public function getTitle(?int $storeId = null): string;

    /**
     * Is enable tokenization.
     *
     * @param ?int $storeId
     */
    public function isEnabledTokenization(?int $storeId = null): bool;

    /**
     * Get allow specific countries.
     *
     * @param ?int $storeId
     */
    public function isAllowSpecific(?int $storeId = null): bool;

    /**
     * Get specific countries for payment method.
     *
     * @param ?int $storeId
     */
    public function getSpecificCountries(?int $storeId = null): string;

    /**
     * Get payment method sort order.
     *
     * @param ?int $storeId
     */
    public function getSortOrder(?int $storeId = null): int;

    /**
     * Get json style for payment method.
     *
     * @param ?int $storeId
     */
    public function getJsonStyle(?int $storeId = null): array;

    /**
     * Get the card input layout: split fields or a single combined input.
     *
     * @param ?int $storeId
     */
    public function getCardInputLayout(?int $storeId = null): string;

    /**
     * Whether the card form renders as separate number / expiry / CVC fields.
     *
     * @param ?int $storeId
     */
    public function isSplitCardInput(?int $storeId = null): bool;
}

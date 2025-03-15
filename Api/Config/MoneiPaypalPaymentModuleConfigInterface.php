<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Config;

/**
 * Get payment method configuration interface.
 */
interface MoneiPaypalPaymentModuleConfigInterface
{
    public const IS_PAYMENT_ENABLED = 'payment/monei_paypal/active';

    public const TITLE = 'payment/monei_paypal/title';

    public const ALLOW_SPECIFIC = 'payment/monei_paypal/allowspecific';

    public const SPECIFIC_COUNTRIES = 'payment/monei_paypal/specificcountry';

    public const SORT_ORDER = 'payment/monei_paypal/sort_order';

    public const JSON_STYLE = 'payment/monei_paypal/json_style';

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
}

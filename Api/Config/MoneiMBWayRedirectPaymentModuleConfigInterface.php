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
 * Get payment method configuration interface for MONEI MBWay Redirect.
 */
interface MoneiMBWayRedirectPaymentModuleConfigInterface
{
    public const IS_PAYMENT_ENABLED = 'payment/monei_mbway_redirect/active';

    public const TITLE = 'payment/monei_mbway_redirect/title';

    public const ALLOW_SPECIFIC = 'payment/monei_mbway_redirect/allowspecific';

    public const SPECIFIC_COUNTRIES = 'payment/monei_mbway_redirect/specificcountry';

    public const SORT_ORDER = 'payment/monei_mbway_redirect/sort_order';

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
}

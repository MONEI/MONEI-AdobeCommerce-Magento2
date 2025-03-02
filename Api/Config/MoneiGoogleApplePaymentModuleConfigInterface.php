<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Config;

/**
 * Get payment method configuration interface.
 */
interface MoneiGoogleApplePaymentModuleConfigInterface
{
    public const IS_PAYMENT_ENABLED = 'payment/monei_google_apple/active';

    public const TITLE = 'payment/monei_google_apple/title';

    public const GOOGLE_TITLE = 'payment/monei_google_apple/title_google_insite';

    public const APPLE_TITLE = 'payment/monei_google_apple/title_apple_insite';

    public const ALLOW_SPECIFIC = 'payment/monei_google_apple/allowspecific';

    public const SPECIFIC_COUNTRIES = 'payment/monei_google_apple/specificcountry';

    public const SORT_ORDER = 'payment/monei_google_apple/sort_order';

    public const JSON_STYLE = 'payment/monei_google_apple/json_style';

    /**
     * Check if payment method is enabled.
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Get payment method title.
     */
    public function getTitle(?int $storeId = null): string;

    /**
     * Get payment method title.
     */
    public function getGoogleTitle(?int $storeId = null): string;

    /**
     * Get payment method title.
     */
    public function getAppleTitle(?int $storeId = null): string;

    /**
     * Get allow specific countries.
     */
    public function isAllowSpecific(?int $storeId = null): bool;

    /**
     * Get specific countries for payment method.
     */
    public function getSpecificCountries(?int $storeId = null): string;

    /**
     * Get payment method sort order.
     */
    public function getSortOrder(?int $storeId = null): int;

    /**
     * Get json style for payment method.
     */
    public function getJsonStyle(?int $storeId = null): array;
}

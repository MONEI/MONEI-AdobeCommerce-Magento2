<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
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

    /**
     * Check if payment method is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(int $storeId = null): bool;

    /**
     * Get payment method title
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTitle(int $storeId = null): string;

    /**
     * Is enable tokenization
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabledTokenization(int $storeId = null): bool;

    /**
     * Get allow specific countries
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAllowSpecific(int $storeId = null): bool;

    /**
     * Get specific countries for payment method
     *
     * @param int|null $storeId
     * @return string
     */
    public function getSpecificCountries(int $storeId = null): string;

    /**
     * Get payment method sort order
     *
     * @param int|null $storeId
     * @return int
     */
    public function getSortOrder(int $storeId = null): int;

    /**
     * Get json style for payment method
     *
     * @param int|null $storeId
     * @return array
     */
    public function getJsonStyle(int $storeId = null): array;
}

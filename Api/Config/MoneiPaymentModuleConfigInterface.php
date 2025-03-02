<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Config;

/**
 * Get payment method configuration interface.
 */
interface MoneiPaymentModuleConfigInterface
{
    public const IS_PAYMENT_ENABLED = 'payment/monei/active';

    public const MODE = 'payment/monei/mode';

    public const TEST_URL = 'payment/monei/test_url';

    public const PRODUCTION_URL = 'payment/monei/production_url';

    public const TEST_ACCOUNT_ID = 'payment/monei/test_account_id';

    public const PRODUCTION_ACCOUNT_ID = 'payment/monei/production_account_id';

    public const TEST_API_KEY = 'payment/monei/test_api_key';

    public const PRODUCTION_API_KEY = 'payment/monei/production_api_key';

    public const LANGUAGE = 'payment/monei/language';

    public const TITLE = 'payment/monei/title';

    public const DESCRIPTION = 'payment/monei/description';

    public const TYPE_OF_PAYMENT = 'payment/monei/type_of_payment';

    public const CONFIRMED_STATUS = 'payment/monei/confirmed_status';

    public const PRE_AUTHORIZED_STATUS = 'payment/monei/pre_authorized_status';

    public const ALLOW_SPECIFIC = 'payment/monei/allowspecific';

    public const SPECIFIC_COUNTRIES = 'payment/monei/specificcountry';

    public const SORT_ORDER = 'payment/monei/sort_order';

    /**
     * Check if payment method is enabled.
     *
     * @param int|null $storeId
     */
    public function isEnabled($storeId = null): bool;

    /**
     * Get payment mode.
     *
     * @param int|null $storeId
     */
    public function getMode($storeId = null): int;

    /**
     * Get test URL.
     *
     * @param int|null $storeId
     */
    public function getUrl($storeId = null): string;

    /**
     * Get test URL.
     *
     * @param int|null $storeId
     */
    public function getTestUrl($storeId = null): string;

    /**
     * Get production URL.
     *
     * @param int|null $storeId
     */
    public function getProductionUrl($storeId = null): string;

    /**
     * Get account id.
     *
     * @param ?int $storeId
     */
    public function getAccountId(?int $storeId = null): string;

    /**
     * Get test account id.
     *
     * @param ?int $storeId
     */
    public function getTestAccountId(?int $storeId = null): string;

    /**
     * Get production account id.
     *
     * @param ?int $storeId
     */
    public function getProductionAccountId(?int $storeId = null): string;

    /**
     * Get test API key.
     *
     * @param int|null $storeId
     */
    public function getApiKey($storeId = null): string;

    /**
     * Get test API key.
     *
     * @param int|null $storeId
     */
    public function getTestApiKey($storeId = null): string;

    /**
     * Get production API key.
     *
     * @param int|null $storeId
     */
    public function getProductionApiKey($storeId = null): string;

    /**
     * Get language.
     *
     * @param ?int $storeId
     */
    public function getLanguage(?int $storeId = null): string;

    /**
     * Get payment method title.
     *
     * @param int|null $storeId
     */
    public function getTitle($storeId = null): string;

    /**
     * Get payment method description.
     *
     * @param int|null $storeId
     */
    public function getDescription($storeId = null): string;

    /**
     * Get type of payment.
     *
     * @param int|null $storeId
     */
    public function getTypeOfPayment($storeId = null): int;

    /**
     * Get confirmed status.
     *
     * @param int|null $storeId
     */
    public function getConfirmedStatus($storeId = null): string;

    /**
     * Get pre-authorized status.
     *
     * @param int|null $storeId
     */
    public function getPreAuthorizedStatus($storeId = null): string;

    /**
     * Get allow specific countries.
     *
     * @param int|null $storeId
     */
    public function isAllowSpecific($storeId = null): bool;

    /**
     * Get specific countries for payment method.
     *
     * @param int|null $storeId
     */
    public function getSpecificCountries($storeId = null): string;

    /**
     * Get payment method sort order.
     *
     * @param int|null $storeId
     */
    public function getSortOrder($storeId = null): int;
}

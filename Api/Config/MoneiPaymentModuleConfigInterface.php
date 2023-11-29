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
interface MoneiPaymentModuleConfigInterface
{
    public const IS_PAYMENT_ENABLED = 'payment/monei/active';

    public const MODE = 'payment/monei/mode';

    public const TEST_URL = 'payment/monei/test_url';

    public const PRODUCTION_URL = 'payment/monei/production_url';

    public const TEST_API_KEY = 'payment/monei/test_api_key';

    public const PRODUCTION_API_KEY = 'payment/monei/production_api_key';

    public const TITLE = 'payment/monei/title';

    public const DESCRIPTION = 'payment/monei/description';

    public const TYPE_OF_CONNECTION = 'payment/monei/type_of_connection';

    public const TYPE_OF_PAYMENT = 'payment/monei/type_of_payment';

    public const CONFIRMED_STATUS = 'payment/monei/confirmed_status';

    public const PRE_AUTHORIZED_STATUS = 'payment/monei/pre_authorized_status';

    public const ALLOW_SPECIFIC = 'payment/monei/allowspecific';

    public const SPECIFIC_COUNTRIES = 'payment/monei/specificcountry';

    public const SORT_ORDER = 'payment/monei/sort_order';

    /**
     * Check if payment method is enabled
     *
     * @param null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool;

    /**
     * Get payment mode
     *
     * @param null $storeId
     * @return int
     */
    public function getMode($storeId = null): int;

    /**
     * Get test URL
     *
     * @param null $storeId
     * @return string
     */
    public function getTestUrl($storeId = null): string;

    /**
     * Get production URL
     *
     * @param null $storeId
     * @return string
     */
    public function getProductionUrl($storeId = null): string;

    /**
     * Get test API key
     *
     * @param null $storeId
     * @return string
     */
    public function getTestApiKey($storeId = null): string;

    /**
     * Get production API key
     *
     * @param null $storeId
     * @return string
     */
    public function getProductionApiKey($storeId = null): string;

    /**
     * Get payment method title
     *
     * @param null $storeId
     * @return string
     */
    public function getTitle($storeId = null): string;

    /**
     * Get payment method description
     *
     * @param null $storeId
     * @return string
     */
    public function getDescription($storeId = null): string;

    /**
     * Get type of connection
     *
     * @param null $storeId
     * @return int
     */
    public function getTypeOfConnection($storeId = null): string;

    /**
     * Get type of payment
     *
     * @param null $storeId
     * @return int
     */
    public function getTypeOfPayment($storeId = null): int;

    /**
     * Get confirmed status
     *
     * @param null $storeId
     * @return string
     */
    public function getConfirmedStatus($storeId = null): string;

    /**
     * Get pre-authorized status
     *
     * @param null $storeId
     * @return string
     */
    public function getPreAuthorizedStatus($storeId = null): string;

    /**
     * Get allow specific countries
     *
     * @param null $storeId
     * @return bool
     */
    public function isAllowSpecific($storeId = null): bool;

    /**
     * Get specific countries for payment method
     *
     * @param null $storeId
     * @return string
     */
    public function getSpecificCountries($storeId = null): string;

    /**
     * Get payment method sort order
     *
     * @param null $storeId
     * @return int
     */
    public function getSortOrder($storeId = null): int;
}

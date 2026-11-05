<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Customer;

/**
 * Links Magento customers to MONEI customers for the account and mode of a store.
 */
interface CustomerServiceInterface
{
    /**
     * Get the mapped MONEI customer ID, or create and map one.
     *
     * @param int $customerId Magento customer ID
     * @param int $storeId Store whose MONEI account and mode apply
     * @param string $email
     * @param string $name
     * @param string $phone
     * @return string|null Null when the customer cannot be linked; never throws
     */
    public function getOrCreateCustomerId(
        int $customerId,
        int $storeId,
        string $email,
        string $name,
        string $phone
    ): ?string;

    /**
     * Remove the mapping for the account and mode of a store, only while it still holds the stale ID.
     *
     * @param int $customerId Magento customer ID
     * @param int $storeId Store whose MONEI account and mode apply
     * @param string $staleCustomerId MONEI customer ID that no longer exists
     * @return void
     */
    public function forgetCustomerId(int $customerId, int $storeId, string $staleCustomerId): void;

    /**
     * Delete every MONEI customer mapped to a Magento customer, for all accounts and modes.
     *
     * @param int $customerId Magento customer ID
     * @return void Never throws
     */
    public function deleteCustomer(int $customerId): void;
}

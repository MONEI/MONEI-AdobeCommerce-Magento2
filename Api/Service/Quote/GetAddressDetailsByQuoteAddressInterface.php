<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Quote;

use Magento\Quote\Api\Data\AddressInterface;

/**
 * Interface for retrieving formatted address details from a quote address
 */
interface GetAddressDetailsByQuoteAddressInterface
{
    /**
     * Get formatted address details from quote address
     *
     * @param AddressInterface $address Quote address to get details from
     * @param string|null $email Optional email override
     *
     * @return array Formatted address details for Monei API
     */
    public function execute(AddressInterface $address, ?string $email = null): array;

    /**
     * Get formatted billing address details from quote address.
     *
     * @param AddressInterface $address Quote address to get details from
     * @param string|null $email Optional email override
     *
     * @return array Formatted billing address details for Monei API
     */
    public function executeBilling(AddressInterface $address, ?string $email = null): array;

    /**
     * Get formatted shipping address details from quote address.
     *
     * @param AddressInterface $address Quote address to get details from
     * @param string|null $email Optional email override
     *
     * @return array Formatted shipping address details for Monei API
     */
    public function executeShipping(AddressInterface $address, ?string $email = null): array;
}
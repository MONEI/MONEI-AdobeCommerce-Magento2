<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Quote;

use Magento\Quote\Api\Data\AddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;

/**
 * Service to get formatted address details from quote address
 */
class GetAddressDetailsByQuoteAddress implements GetAddressDetailsByQuoteAddressInterface
{
    /**
     * @inheritdoc
     */
    public function execute(AddressInterface $address, ?string $email = null): array
    {
        if (!$address->getId()) {
            return [];
        }

        $streetAddress = $address->getStreet();
        $moneiAddress = [
            'name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'email' => $address->getEmail() ?: $email,
            'phone' => $address->getTelephone(),
            'company' => ($address->getCompany() ?? ''),
            'address' => [
                'country' => $address->getCountryId(),
                'city' => $address->getCity(),
                'line1' => ($streetAddress[0] ?? $streetAddress),
                'line2' => ($streetAddress[1] ?? ''),
                'zip' => $address->getPostcode(),
                'state' => $address->getRegion(),
            ],
        ];

        // Remove empty fields
        if (empty($moneiAddress['company'])) {
            unset($moneiAddress['company']);
        }

        if (empty($moneiAddress['address']['line2'])) {
            unset($moneiAddress['address']['line2']);
        }

        return $moneiAddress;
    }

    /**
     * Get formatted billing address details from quote address.
     *
     * @param AddressInterface $address Quote address to get details from
     * @param string|null $email Optional email override
     *
     * @return array Formatted billing address details for Monei API
     */
    public function executeBilling(AddressInterface $address, ?string $email = null): array
    {
        return $this->execute($address, $email);
    }

    /**
     * Get formatted shipping address details from quote address.
     *
     * @param AddressInterface $address Quote address to get details from
     * @param string|null $email Optional email override
     *
     * @return array Formatted shipping address details for Monei API
     */
    public function executeShipping(AddressInterface $address, ?string $email = null): array
    {
        return $this->execute($address, $email);
    }
}

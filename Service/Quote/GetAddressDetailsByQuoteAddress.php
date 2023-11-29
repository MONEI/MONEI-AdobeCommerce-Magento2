<?php

/**
 * @author Interactiv4 Team
 * @copyright Copyright © Interactiv4 (https://www.interactiv4.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Quote;

use Magento\Quote\Api\Data\AddressInterface;

class GetAddressDetailsByQuoteAddress
{

    public function execute(AddressInterface $address, string $email = null): array
    {
        if (!$address->getId()) {
            return [];
        }

        $streetAddress = $address->getStreet();
        $moneiAddress = [
            "name"      => $address->getFirstname() . ' ' . $address->getLastname(),
            "email"     => $address->getEmail()?: $email,
            "phone"     => $address->getTelephone(),
            "company"   => ($address->getCompany() ?? ''),
            "address"   => [
                "country"   => $address->getCountryId(),
                "city"      => $address->getCity(),
                "line1"     => ($streetAddress[0] ?? $streetAddress),
                "line2"     => ($streetAddress[1] ?? ''),
                "zip"       => $address->getPostcode(),
                "state"     => $address->getRegion(),
            ]
        ];

        if (!$moneiAddress['company']) {
            unset($moneiAddress['company']);
        }

        if (!$moneiAddress['address']['line2']) {
            unset($moneiAddress['address']['line2']);
        }

        return $moneiAddress;
    }
}

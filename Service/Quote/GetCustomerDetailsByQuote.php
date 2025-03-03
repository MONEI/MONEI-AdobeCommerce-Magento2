<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Quote;

use Magento\Quote\Api\Data\CartInterface;

class GetCustomerDetailsByQuote
{
    /**
     * Get customer details from quote.
     *
     * @param CartInterface $quote Quote to get customer details from
     * @param string|null $email Optional email override
     *
     * @return array Customer details including email, name, and phone
     */
    public function execute(CartInterface $quote, ?string $email = null): array
    {
        if (!$quote->getEntityId()) {
            return [];
        }

        $address = $quote->getShippingAddress() ?: $quote->getBillingAddress();

        $customerName = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
        $addressEmail = $address ? $address->getEmail() : '';
        $addressCustomerName = $address ? $address->getFirstname() . ' ' . $address->getLastname() : '';

        return [
            'email' => $email ?: ($quote->getCustomerEmail() ?? $addressEmail),
            'name' => !empty(trim($customerName))
                ? $customerName
                : $addressCustomerName,
            'phone' => $address ? $address->getTelephone() : '',
        ];
    }
}

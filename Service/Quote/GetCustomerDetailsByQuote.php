<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Quote;

use Magento\Quote\Api\Data\CartInterface;

class GetCustomerDetailsByQuote
{

    public function execute(CartInterface $quote, string $email = null): array
    {
        if (!$quote->getEntityId()) {
            return [];
        }

        $address = $quote->getShippingAddress() ?: $quote->getBillingAddress();

        $customerName = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
        return [
            "email" => $email ?: ($quote->getCustomerEmail() ?? $address?->getEmail()),
            "name"  => !empty(trim($customerName))
                ? $customerName
                : $address?->getFirstname() . ' ' . $address?->getLastname(),
            "phone" => $address?->getTelephone() ?: ''
        ];
    }
}

<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Quote;

use Magento\Quote\Api\Data\CartInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;

/**
 * Service to get customer details from quote
 */
class GetCustomerDetailsByQuote implements GetCustomerDetailsByQuoteInterface
{
    /**
     * @inheritdoc
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

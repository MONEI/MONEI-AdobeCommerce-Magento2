<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Api\Service\Quote;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Interface for retrieving customer details from a quote
 */
interface GetCustomerDetailsByQuoteInterface
{
    /**
     * Get customer details from quote
     *
     * @param CartInterface $quote Quote to get customer details from
     * @param string|null $email Optional email override
     *
     * @return array Customer details including email, name, and phone
     */
    public function execute(CartInterface $quote, ?string $email = null): array;
}
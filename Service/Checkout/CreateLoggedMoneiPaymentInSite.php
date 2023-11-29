<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote;

/**
 * Monei create payment REST integration service class.
 */
class CreateLoggedMoneiPaymentInSite implements CreateLoggedMoneiPaymentInSiteInterface
{
    public function __construct(
        private readonly CartRepositoryInterface         $quoteRepository,
        private readonly Session                         $checkoutSession,
        private readonly GetCustomerDetailsByQuote       $getCustomerDetailsByQuote,
        private readonly GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress,
        private readonly CreatePayment                   $createPayment)
    {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(string $cartId, string $email): array
    {
        $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($cartId);
        if (!$quote) {
            throw new LocalizedException(__('An error occurred to retrieve the information about the quote'));
        }

        // Save the quote in order to avoid that the other processes can reserve the order
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        $data = [
            "amount" => $quote->getBaseGrandTotal() * 100,
            "currency" => $quote->getBaseCurrencyCode(),
            "orderId" => $quote->getReservedOrderId(),
            "customer" => $this->getCustomerDetailsByQuote->execute($quote),
            "billingDetails" => $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress()),
            "shippingDetails" => $this->getAddressDetailsByQuoteAddress->execute($quote->getShippingAddress()),
        ];

        $result = $this->createPayment->execute($data);
        $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?: '');
        $this->quoteRepository->save($quote);

        return [$result];
    }
}

<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
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
    private CartRepositoryInterface $quoteRepository;

    private Session $checkoutSession;

    private GetCustomerDetailsByQuote $getCustomerDetailsByQuote;

    private GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress;

    private CreatePayment $createPayment;

    /**
     * Constructor.
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetCustomerDetailsByQuote $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress,
        CreatePayment $createPayment
    ) {
        $this->createPayment = $createPayment;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @throws LocalizedException
     */
    public function execute(string $cartId, string $email): array
    {
        $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($cartId);
        if (!$quote) {
            throw new LocalizedException(new Phrase('An error occurred to retrieve the information about the quote'));
        }

        // Save the quote in order to avoid that the other processes can reserve the order
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        $data = [
            'amount' => $quote->getBaseGrandTotal() * 100,
            'currency' => $quote->getBaseCurrencyCode(),
            'orderId' => $quote->getReservedOrderId(),
            'customer' => $this->getCustomerDetailsByQuote->execute($quote),
            'billingDetails' => $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress()),
            'shippingDetails' => $this->getAddressDetailsByQuoteAddress->execute($quote->getShippingAddress()),
        ];

        try {
            $result = $this->createPayment->execute($data);
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?: '');
            $this->quoteRepository->save($quote);

            return [$result];
        } catch (\Exception $e) {
            throw new LocalizedException(new Phrase('An error occurred rendering the pay with card. Please try again later.'));
        }
    }
}

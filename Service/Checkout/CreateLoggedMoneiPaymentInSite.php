<?php

/**
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
    /**
     * Quote repository for managing shopping carts.
     *
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * Checkout session for accessing current quote data.
     *
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * Service for retrieving customer details from quote.
     *
     * @var GetCustomerDetailsByQuote
     */
    private GetCustomerDetailsByQuote $getCustomerDetailsByQuote;

    /**
     * Service for retrieving address details from quote address.
     *
     * @var GetAddressDetailsByQuoteAddress
     */
    private GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress;

    /**
     * Service for creating Monei payments.
     *
     * @var CreatePayment
     */
    private CreatePayment $createPayment;

    /**
     * Constructor.
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param Session $checkoutSession
     * @param GetCustomerDetailsByQuote $getCustomerDetailsByQuote
     * @param GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress
     * @param CreatePayment $createPayment
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
     * Creates a Monei payment for a logged-in customer's cart.
     *
     * @param string $cartId The ID of the customer's shopping cart
     * @param string $email The customer's email address
     *
     * @throws LocalizedException If the quote cannot be retrieved or payment creation fails
     *
     * @return array The payment creation result containing payment details
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
            throw new LocalizedException(
                __('An error occurred rendering the pay with card. Please try again later.')
            );
        }
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateGuestMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote;
use Magento\Framework\Phrase;

/**
 * Monei create payment in site REST integration service class.
 */
class CreateGuestMoneiPaymentInSite implements CreateGuestMoneiPaymentInSiteInterface
{
    /**
     * Quote repository for accessing and saving quotes.
     *
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * Checkout session for accessing the current quote.
     *
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * Service to convert masked quote ID to quote ID.
     *
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    /**
     * Service to get customer details from a quote.
     *
     * @var GetCustomerDetailsByQuote
     */
    private GetCustomerDetailsByQuote $getCustomerDetailsByQuote;

    /**
     * Service to get address details from a quote address.
     *
     * @var GetAddressDetailsByQuoteAddress
     */
    private GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress;

    /**
     * Service to create a payment in Monei.
     *
     * @var CreatePayment
     */
    private CreatePayment $createPayment;

    /**
     * Constructor for CreateGuestMoneiPaymentInSite.
     *
     * @param CartRepositoryInterface $quoteRepository Repository for accessing and saving quotes
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId Service to convert masked quote ID to quote ID
     * @param GetCustomerDetailsByQuote $getCustomerDetailsByQuote Service to get customer details from a quote
     * @param GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress Service to get address details
     * @param CreatePayment $createPayment Service to create a payment in Monei
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        GetCustomerDetailsByQuote $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress,
        CreatePayment $createPayment
    ) {
        $this->createPayment = $createPayment;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Create payment for guest customer.
     *
     * @param string $cartId Masked cart ID
     * @param string $email  Customer email
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(string $cartId, string $email): array
    {
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($quoteId);
        if (!$quote) {
            throw new LocalizedException(new Phrase('An error occurred to retrieve the information about the quote'));
        }
        // Save the quote in order to avoid that the other processes can reserve the order
        if (method_exists($quote, 'reserveOrderId')) {
            $quote->reserveOrderId();
        }
        $this->quoteRepository->save($quote);

        $data = [
            'amount' => method_exists($quote, 'getBaseGrandTotal') ? $quote->getBaseGrandTotal() * 100 : 0,
            'currency' => method_exists($quote, 'getBaseCurrencyCode') ? $quote->getBaseCurrencyCode() : 'EUR',
            'orderId' => $quote->getReservedOrderId(),
            'customer' => $this->getCustomerDetailsByQuote->execute($quote, $email),
            'billingDetails' => $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress(), $email),
            'shippingDetails' => method_exists($quote, 'getShippingAddress') && $quote->getShippingAddress() ?
                $this->getAddressDetailsByQuoteAddress->execute($quote->getShippingAddress(), $email) :
                $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress(), $email),
        ];

        try {
            $result = $this->createPayment->execute($data);
            if (method_exists($quote, 'setData')) {
                $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?: '');
                $this->quoteRepository->save($quote);
            }

            return [$result];
        } catch (\Exception $e) {
            throw new LocalizedException(
                new Phrase('An error occurred rendering the pay with card. Please try again later.')
            );
        }
    }
}

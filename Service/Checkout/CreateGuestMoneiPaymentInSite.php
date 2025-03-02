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

/**
 * Monei create payment in site REST integration service class.
 */
class CreateGuestMoneiPaymentInSite implements CreateGuestMoneiPaymentInSiteInterface
{
    private CartRepositoryInterface $quoteRepository;

    private Session $checkoutSession;

    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    private GetCustomerDetailsByQuote $getCustomerDetailsByQuote;

    private GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress;

    private CreatePayment $createPayment;

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
            throw new LocalizedException(__('An error occurred to retrieve the information about the quote'));
        }
        // Save the quote in order to avoid that the other processes can reserve the order
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        $data = [
            'amount' => $quote->getBaseGrandTotal() * 100,
            'currency' => $quote->getBaseCurrencyCode(),
            'orderId' => $quote->getReservedOrderId(),
            'customer' => $this->getCustomerDetailsByQuote->execute($quote, $email),
            'billingDetails' => $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress(), $email),
            'shippingDetails' => $this->getAddressDetailsByQuoteAddress->execute($quote->getShippingAddress(), $email),
        ];

        try {
            $result = $this->createPayment->execute($data);
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?: '');
            $this->quoteRepository->save($quote);

            return [$result];
        } catch (\Exception $e) {
            throw new LocalizedException(__('An error occurred rendering the pay with card. Please try again later.'));
        }
    }
}

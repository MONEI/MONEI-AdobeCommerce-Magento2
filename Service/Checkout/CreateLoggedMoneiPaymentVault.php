<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface as QuoteInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentVaultInterface;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote;

/**
 * Service class to create a Monei payment using a saved payment token for logged-in customers.
 *
 * This class handles the creation of payments using saved card details (vault) for authenticated customers,
 * retrieving the necessary customer, billing, and shipping information from the quote.
 */
class CreateLoggedMoneiPaymentVault implements CreateLoggedMoneiPaymentVaultInterface
{
    /**
     * Quote repository for managing quote data.
     *
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * Checkout session to access current quote.
     *
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * Service to get customer details from quote.
     *
     * @var GetCustomerDetailsByQuote
     */
    private GetCustomerDetailsByQuote $getCustomerDetailsByQuote;

    /**
     * Service to get address details from quote address.
     *
     * @var GetAddressDetailsByQuoteAddress
     */
    private GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress;

    /**
     * Payment token management for handling saved payment methods.
     *
     * @var PaymentTokenManagementInterface
     */
    private PaymentTokenManagementInterface $tokenManagement;

    /**
     * Service to create payment in Monei.
     *
     * @var CreatePayment
     */
    private CreatePayment $createPayment;

    /**
     * Constructor for CreateLoggedMoneiPaymentVault.
     *
     * @param CartRepositoryInterface $quoteRepository Repository for managing quote data
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param GetCustomerDetailsByQuote $getCustomerDetailsByQuote Service to retrieve customer details
     * @param GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress Service to retrieve address details
     * @param PaymentTokenManagementInterface $tokenManagement For handling saved payment methods
     * @param CreatePayment $createPayment Service to create payment in Monei
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetCustomerDetailsByQuote $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress,
        PaymentTokenManagementInterface $tokenManagement,
        CreatePayment $createPayment
    ) {
        $this->createPayment = $createPayment;
        $this->tokenManagement = $tokenManagement;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Create a Monei payment using a saved payment token.
     *
     * @param string $cartId     The ID of the cart to process
     * @param string $publicHash The public hash of the saved payment token
     *
     * @throws LocalizedException If there are issues retrieving the quote, token, or creating the payment
     *
     * @return array Payment creation result array containing payment details and token
     */
    public function execute(string $cartId, string $publicHash): array
    {
        $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($cartId);
        if (!$quote) {
            throw new LocalizedException(new Phrase('An error occurred to retrieve the information about the quote'));
        }

        $paymentToken = $this->tokenManagement->getByPublicHash($publicHash, $quote->getCustomerId());
        if (!$paymentToken) {
            throw new LocalizedException(new Phrase('It is not possible to make the payment with the saved card.'));
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
            'customer' => $this->getCustomerDetailsByQuote->execute($quote),
            'billingDetails' => $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress()),
            'shippingDetails' => method_exists($quote, 'getShippingAddress') && $quote->getShippingAddress() ?
                $this->getAddressDetailsByQuoteAddress->execute($quote->getShippingAddress()) :
                $this->getAddressDetailsByQuoteAddress->execute($quote->getBillingAddress()),
        ];

        try {
            $result = $this->createPayment->execute($data);
            if (method_exists($quote, 'setData')) {
                $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?: '');
                $this->quoteRepository->save($quote);
            }

            $result['paymentToken'] = $paymentToken->getGatewayToken();

            return [$result];
        } catch (\Exception $e) {
            throw new LocalizedException(
                new Phrase('An error occurred rendering the pay with card. Please try again later.')
            );
        }
    }
}

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
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Service\AbstractApiService;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote;

/**
 * Monei create payment REST integration service class for logged-in customers.
 *
 * Handles payment creation for customers who are logged into their accounts,
 * utilizing their stored customer information.
 */
class CreateLoggedMoneiPaymentInSite extends AbstractApiService implements CreateLoggedMoneiPaymentInSiteInterface
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
     * @param Logger $logger Logger for tracking operations
     * @param CartRepositoryInterface $quoteRepository Repository for accessing and saving quotes
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param GetCustomerDetailsByQuote $getCustomerDetailsByQuote Service to get customer details from a quote
     * @param GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress Service to get address details
     * @param CreatePayment $createPayment Service for creating MONEI payments
     */
    public function __construct(
        Logger $logger,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetCustomerDetailsByQuote $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress,
        CreatePayment $createPayment
    ) {
        parent::__construct($logger);
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->createPayment = $createPayment;
    }

    /**
     * Creates a Monei payment for a logged-in customer's cart.
     *
     * @param string $cartId The ID of the customer's shopping cart
     * @param string $email The customer's email address
     *
     * @return array The payment creation result containing payment details
     * @throws LocalizedException If the quote cannot be retrieved or payment creation fails
     */
    public function execute(string $cartId, string $email): array
    {
        // First, resolve the quote from cart ID
        $quote = $this->resolveQuote($cartId);

        // Reserve order ID to prevent race conditions
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        return $this->executeApiCall(__METHOD__, function () use ($quote) {
            // Prepare payment data
            $paymentData = $this->preparePaymentData($quote);

            // Create payment
            $result = $this->createPayment->execute($paymentData);

            // Save payment ID to quote for future reference
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?? '');
            $this->quoteRepository->save($quote);

            return [$result];
        }, [
            'cartId' => $cartId,
            'quote_id' => $quote->getId(),
            'reserved_order_id' => $quote->getReservedOrderId()
        ]);
    }

    /**
     * Resolve a quote from cart ID
     *
     * @param string $cartId Cart ID
     * @return Quote The resolved quote
     * @throws LocalizedException If the quote cannot be resolved
     */
    private function resolveQuote(string $cartId): Quote
    {
        try {
            // For logged-in users, try to get from session first, then fallback to repository
            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                $quote = $this->quoteRepository->get($cartId);
            }

            if (!$quote || !$quote->getId()) {
                throw new LocalizedException(__('Could not load quote information'));
            }

            return $quote;
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Quote not found: ' . $e->getMessage(), ['cartId' => $cartId]);

            throw new LocalizedException(__('Quote not found for this cart ID'));
        } catch (\Exception $e) {
            $this->logger->error('Error resolving quote: ' . $e->getMessage(), ['cartId' => $cartId]);

            throw new LocalizedException(__('An error occurred while retrieving quote information'));
        }
    }

    /**
     * Prepare payment data from quote
     *
     * @param Quote $quote The quote to prepare payment data from
     * @return array Payment data ready for the CreatePayment service
     */
    private function preparePaymentData(Quote $quote): array
    {
        // Get shipping address or fallback to billing if shipping is not available
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress || !$shippingAddress->getId()) {
            $shippingAddress = $quote->getBillingAddress();
        }

        return [
            'amount' => (int)($quote->getBaseGrandTotal() * 100), // Convert to cents
            'currency' => $quote->getBaseCurrencyCode(),
            'order_id' => $quote->getReservedOrderId(),
            'customer' => $this->getCustomerDetailsByQuote->execute($quote),
            'billing_details' => $this->getAddressDetailsByQuoteAddress->executeBilling($quote->getBillingAddress()),
            'shipping_details' => $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress),
        ];
    }
}

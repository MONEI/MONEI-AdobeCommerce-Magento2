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
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentVaultInterface;
use Monei\MoneiPayment\Service\AbstractApiService;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;

/**
 * Service class to create a Monei payment using a saved payment token for logged-in customers.
 *
 * This class handles the creation of payments using saved card details (vault) for authenticated customers,
 * retrieving the necessary customer, billing, and shipping information from the quote.
 */
class CreateLoggedMoneiPaymentVault extends AbstractApiService implements CreateLoggedMoneiPaymentVaultInterface
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
     * @var GetCustomerDetailsByQuoteInterface
     */
    private GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote;

    /**
     * Service to get address details from quote address.
     *
     * @var GetAddressDetailsByQuoteAddressInterface
     */
    private GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress;

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
     * @param Logger $logger Logger for tracking operations
     * @param CartRepositoryInterface $quoteRepository Repository for managing quote data
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote Service to retrieve customer details
     * @param GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress Service to retrieve address details
     * @param PaymentTokenManagementInterface $tokenManagement For handling saved payment methods
     * @param CreatePayment $createPayment Service to create payment in Monei
     */
    public function __construct(
        Logger $logger,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress,
        PaymentTokenManagementInterface $tokenManagement,
        CreatePayment $createPayment
    ) {
        parent::__construct($logger);
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->tokenManagement = $tokenManagement;
        $this->createPayment = $createPayment;
    }

    /**
     * Create a Monei payment using a saved payment token.
     *
     * @param string $cartId     The ID of the cart to process
     * @param string $publicHash The public hash of the saved payment token
     *
     * @return array Payment creation result array containing payment details and token
     * @throws LocalizedException If there are issues retrieving the quote, token, or creating the payment
     */
    public function execute(string $cartId, string $publicHash): array
    {
        // First, resolve the quote and validate payment token
        list($quote, $paymentToken) = $this->resolveQuoteAndToken($cartId, $publicHash);

        // Reserve order ID to prevent race conditions
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        return $this->executeApiCall(__METHOD__, function () use ($quote, $paymentToken) {
            // Prepare payment data
            $paymentData = $this->preparePaymentData($quote);

            // Create payment
            $result = $this->createPayment->execute($paymentData);

            // Save payment ID to quote for future reference
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?? '');
            $this->quoteRepository->save($quote);

            // Add token information to response for the frontend
            $result['paymentToken'] = $paymentToken->getGatewayToken();

            return [$result];
        }, [
            'cartId' => $cartId,
            'publicHash' => $publicHash,
            'customerId' => $quote->getCustomerId(),
            'quote_id' => $quote->getId(),
            'reserved_order_id' => $quote->getReservedOrderId()
        ]);
    }

    /**
     * Resolve quote and payment token
     *
     * @param string $cartId The cart ID to process
     * @param string $publicHash The public hash of the saved payment token
     * @return array [$quote, $paymentToken]
     * @throws LocalizedException If quote or token cannot be resolved
     */
    private function resolveQuoteAndToken(string $cartId, string $publicHash): array
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

            // Verify customer is logged in
            if (!$quote->getCustomerId()) {
                throw new LocalizedException(__('Customer must be logged in to use saved cards'));
            }

            // Get the payment token
            $paymentToken = $this->tokenManagement->getByPublicHash($publicHash, $quote->getCustomerId());
            if (!$paymentToken || !$paymentToken->getEntityId()) {
                throw new LocalizedException(__('The requested saved card was not found'));
            }

            return [$quote, $paymentToken];
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Quote or token not found: ' . $e->getMessage(), [
                'cartId' => $cartId,
                'publicHash' => $publicHash
            ]);

            throw new LocalizedException(__('Quote or payment card information not found'));
        } catch (\Exception $e) {
            $this->logger->error('Error resolving quote or token: ' . $e->getMessage(), [
                'cartId' => $cartId,
                'publicHash' => $publicHash
            ]);

            throw new LocalizedException(__('An error occurred retrieving payment information'));
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
            // Add an indicator that we're using a saved token/card
            'metadata' => [
                'payment_source' => 'vault',
                'customer_id' => (string)$quote->getCustomerId()
            ]
        ];
    }
}

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
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentVaultInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service class to create a Monei payment using a saved payment token for logged-in customers.
 *
 * This class handles the creation of payments using saved card details (vault) for authenticated customers,
 * retrieving the necessary customer, billing, and shipping information from the quote.
 */
class CreateLoggedMoneiPaymentVault extends AbstractCheckoutService implements CreateLoggedMoneiPaymentVaultInterface
{
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
     * @param ApiExceptionHandler|null $exceptionHandler Exception handler for API calls
     * @param MoneiApiClient|null $apiClient MONEI API client
     * @param CartRepositoryInterface $quoteRepository Repository for managing quote data
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote Service to retrieve customer details
     * @param GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress Service to retrieve address details
     * @param PaymentTokenManagementInterface $tokenManagement For handling saved payment methods
     * @param CreatePayment $createPayment Service to create payment in Monei
     * @param GetPaymentInterface $getPaymentService Service to retrieve payment details
     */
    public function __construct(
        Logger $logger,
        ?ApiExceptionHandler $exceptionHandler,
        ?MoneiApiClient $apiClient,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress,
        PaymentTokenManagementInterface $tokenManagement,
        CreatePayment $createPayment,
        GetPaymentInterface $getPaymentService
    ) {
        parent::__construct(
            $logger,
            $exceptionHandler,
            $apiClient,
            $quoteRepository,
            $checkoutSession,
            $getPaymentService
        );
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
     * @return array Payment creation result containing payment details
     * @throws LocalizedException If there are issues retrieving the quote, token, or creating the payment
     */
    public function execute(string $cartId, string $publicHash)
    {
        // First, resolve the quote and validate payment token
        list($quote, $paymentToken) = $this->resolveQuoteAndToken($cartId, $publicHash);

        // Reserve order ID to prevent race conditions
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        // Check if the quote already has a payment ID to prevent double payment
        $existingPayment = $this->checkExistingPayment($quote);
        if ($existingPayment !== null) {
            return $existingPayment;
        }

        return $this->executeApiCall(__METHOD__, function () use ($quote, $paymentToken) {
            // Prepare payment data
            $paymentData = $this->preparePaymentData($quote, $paymentToken);

            // Create payment
            $result = $this->createPayment->execute($paymentData);
            $paymentId = $result->getId() ?? '';

            // Save payment ID to quote for future reference
            $this->savePaymentIdToQuote($quote, $paymentId);

            // Return a properly formatted array with payment properties as expected by the frontend
            return $this->createPaymentResult($paymentId, [
                'paymentToken' => $paymentToken->getGatewayToken()
            ]);
        }, [
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
            // Use the parent class method to resolve the quote
            $quote = $this->resolveQuote($cartId);

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
     * Prepare payment data from quote and token
     *
     * @param Quote $quote The quote to prepare payment data from
     * @param PaymentTokenInterface $paymentToken The vault payment token to use
     * @return array Payment data ready for the CreatePayment service
     */
    private function preparePaymentData(Quote $quote, PaymentTokenInterface $paymentToken): array
    {
        // Get shipping address or fallback to billing if shipping is not available
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress || !$shippingAddress->getId()) {
            $shippingAddress = $quote->getBillingAddress();
        }

        // Start with the base payment data
        $paymentData = $this->prepareBasePaymentData($quote);

        // Add customer-specific data
        $paymentData['customer'] = $this->getCustomerDetailsByQuote->execute($quote);
        $paymentData['billing_details'] = $this->getAddressDetailsByQuoteAddress->executeBilling($quote->getBillingAddress());
        $paymentData['shipping_details'] = $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress);

        return $paymentData;
    }
}

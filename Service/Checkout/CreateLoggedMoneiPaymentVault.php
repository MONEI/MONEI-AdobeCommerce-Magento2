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
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Api\Service\ConfirmPaymentInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
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
     * @var CreatePaymentInterface
     */
    private CreatePaymentInterface $createPayment;

    /**
     * Service to confirm payment in Monei.
     *
     * @var ConfirmPaymentInterface
     */
    private ConfirmPaymentInterface $confirmPayment;

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
     * @param CreatePaymentInterface $createPayment Service to create payment in Monei
     * @param GetPaymentInterface $getPaymentService Service to retrieve payment details
     * @param ConfirmPaymentInterface $confirmPayment Service to confirm payment in Monei
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
        CreatePaymentInterface $createPayment,
        GetPaymentInterface $getPaymentService,
        ConfirmPaymentInterface $confirmPayment
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
        $this->confirmPayment = $confirmPayment;
    }

    /**
     * Create a Monei payment using a saved payment token.
     *
     * @param string $cartId     The ID of the cart to process
     * @param string $publicHash The public hash of the saved payment token
     *
     * @return array Simple response array with direct access to redirectUrl
     * @throws LocalizedException If there are issues retrieving the quote, token, or creating the payment
     */
    public function execute(string $cartId, string $publicHash)
    {
        // First, resolve the quote and validate payment token
        list($quote, $paymentToken) = $this->resolveQuoteAndToken($cartId, $publicHash);

        // Reserve order ID to prevent race conditions
        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
            $this->quoteRepository->save($quote);
        }

        // Check if the quote already has a payment ID to prevent double payment
        $existingPayment = $this->checkExistingPaymentWithToken($quote, $paymentToken);
        if ($existingPayment !== null) {
            return $existingPayment;
        }

        return $this->executeApiCall(__METHOD__, function () use ($quote, $paymentToken) {
            // Prepare payment data
            $paymentData = $this->preparePaymentData($quote, $paymentToken);

            // If we have a reserved order ID, use it
            if ($quote->getReservedOrderId()) {
                $paymentData['order_id'] = $quote->getReservedOrderId();
            }

            // Create payment
            $result = $this->createPayment->execute($paymentData);
            $paymentId = $result->getId() ?? '';

            // Save payment ID to quote for future reference
            $this->savePaymentIdToQuote($quote, $paymentId);

            // Return in the format expected by VaultRedirect.php controller
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'redirect_url' => $result->getNextAction() ? $result->getNextAction()->getRedirectUrl() : null
            ];
        }, [
            'public_hash' => $publicHash,
            'customer_id' => $quote->getCustomerId(),
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
     * Checks if a quote already has a payment ID
     *
     * @param Quote $quote The quote to check
     * @return array|null Returns payment data array if valid payment exists, null otherwise
     */
    protected function checkExistingPayment(Quote $quote): ?array
    {
        return $this->checkExistingPaymentWithToken($quote, null);
    }

    /**
     * Checks if a quote already has a payment ID and confirms it if it does, using the provided token
     *
     * @param Quote $quote The quote to check
     * @param PaymentTokenInterface|null $paymentToken The vault payment token to use
     * @return array|null Returns payment data array if valid payment exists, null otherwise
     */
    protected function checkExistingPaymentWithToken(Quote $quote, ?PaymentTokenInterface $paymentToken): ?array
    {
        $existingPaymentId = parent::checkExistingPayment($quote);
        if ($existingPaymentId === null || $paymentToken === null) {
            return $existingPaymentId;
        }

        return $this->executeApiCall(__METHOD__, function () use ($quote, $paymentToken, $existingPaymentId) {
            // Extract the payment ID from the result array
            $paymentId = $existingPaymentId[0]['id'] ?? '';
            if (empty($paymentId)) {
                return null;
            }

            // Prepare payment data for confirmation
            $confirmData = $this->preparePaymentData($quote, $paymentToken);
            $confirmData['payment_id'] = $paymentId;

            // Confirm the existing payment
            $result = $this->confirmPayment->execute($confirmData);

            // Return the payment result in the same format as execute() method for consistency
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'redirect_url' => $result->getNextAction() ? $result->getNextAction()->getRedirectUrl() : null
            ];
        }, [
            'payment_id' => $existingPaymentId[0]['id'] ?? '',
            'quote_id' => $quote->getId(),
            'customer_id' => $quote->getCustomerId(),
            'reserved_order_id' => $quote->getReservedOrderId()
        ]);
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

        // Add payment token to additional data
        $paymentData['payment_token'] = $paymentToken->getGatewayToken();

        // Add customer-specific data
        $paymentData['customer'] = $this->getCustomerDetailsByQuote->execute($quote);
        $paymentData['billing_details'] = $this->getAddressDetailsByQuoteAddress->executeBilling($quote->getBillingAddress());
        $paymentData['shipping_details'] = $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress);

        return $paymentData;
    }

    /**
     * Create a standard result array for payment data
     *
     * @param string $paymentId      The Monei payment ID
     * @param array  $additionalData Additional data to include in the result
     * @return array The result array with payment data
     */
    protected function createPaymentResult(string $paymentId, array $additionalData = []): array
    {
        // Return a simplified response structure with snake_case properties
        return array_merge([
            'success' => true,
            'payment_id' => $paymentId
        ], $additionalData);
    }
}

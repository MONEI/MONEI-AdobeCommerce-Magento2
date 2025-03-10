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
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateGuestMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Logger;

/**
 * Monei create payment in site REST integration service class.
 *
 * Creates a payment for a guest customer by processing their cart information
 * and communicating with the MONEI API.
 */
class CreateGuestMoneiPaymentInSite extends AbstractCheckoutService implements CreateGuestMoneiPaymentInSiteInterface
{
    /**
     * Service to convert masked quote ID to quote ID.
     *
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    /**
     * Service to get customer details from a quote.
     *
     * @var GetCustomerDetailsByQuoteInterface
     */
    private GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote;

    /**
     * Service to get address details from a quote address.
     *
     * @var GetAddressDetailsByQuoteAddressInterface
     */
    private GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress;

    /**
     * Module configuration.
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * Create Payment Service.
     *
     * @var CreatePayment
     */
    private CreatePayment $createPayment;

    /**
     * Constructor for CreateGuestMoneiPaymentInSite.
     *
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for API calls
     * @param MoneiApiClient $apiClient MONEI API client
     * @param CartRepositoryInterface $quoteRepository Repository for accessing and saving quotes
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId Service to convert masked quote ID to quote ID
     * @param GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote Service to get customer details from a quote
     * @param GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress Service to get address details
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration
     * @param CreatePayment $createPayment Service for creating MONEI payments
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        CreatePayment $createPayment
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient, $quoteRepository, $checkoutSession);
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->moduleConfig = $moduleConfig;
        $this->createPayment = $createPayment;
    }

    /**
     * Create payment for guest customer.
     *
     * @param string $cartId Masked cart ID
     * @param string $email  Customer email
     *
     * @return array Payment data from MONEI API
     * @throws LocalizedException If any error occurs during payment creation
     * @throws NoSuchEntityException If the quote cannot be found
     */
    public function execute(string $cartId, string $email)
    {
        // First, resolve the quote from the masked cart ID
        $quote = $this->resolveQuoteFromMaskedId($cartId);

        // Reserve order ID to prevent race conditions
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        // Check if the quote already has a payment ID to prevent double payment
        $existingPayment = $this->checkExistingPayment($quote);
        if ($existingPayment !== null) {
            return $existingPayment;
        }

        // Use the executeApiCall to wrap the payment creation process
        return $this->executeApiCall(__METHOD__, function () use ($quote, $email) {
            // Build the payment request from the quote and email
            $paymentData = $this->buildPaymentRequest($quote, $email);

            // Create the payment using the centralized CreatePayment service
            $result = $this->createPayment->execute($paymentData);
            $paymentId = $result->getId() ?? '';

            // Save payment ID to quote for future reference
            $this->savePaymentIdToQuote($quote, $paymentId);

            // Return a properly formatted payment result
            return $this->createPaymentResult($paymentId);
        }, [
            'cartId' => $cartId,
            'email' => $email,
            'quote_id' => $quote->getId(),
            'reserved_order_id' => $quote->getReservedOrderId()
        ]);
    }

    /**
     * Resolve a quote from masked cart ID
     *
     * @param string $cartId Masked cart ID
     * @return Quote The resolved quote
     * @throws LocalizedException If the quote cannot be resolved
     */
    private function resolveQuoteFromMaskedId(string $cartId): Quote
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($quoteId);

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
     * Prepare payment data for MONEI API
     *
     * @param Quote $quote The quote to create payment for
     * @param string $email Customer email
     * @return array Payment data ready for the CreatePayment service
     */
    private function buildPaymentRequest(Quote $quote, string $email): array
    {
        // Log API settings for debugging
        $this->logger->debug('MONEI API Configuration', [
            'monei_api_url' => $this->moduleConfig->getUrl(),
            'module_config' => [
                'is_test_mode' => $this->moduleConfig->getMode() === 1 ? 'Yes' : 'No'
            ]
        ]);

        // Get shipping address or fallback to billing if shipping is not available
        $shippingAddress = $quote->getShippingAddress() ?: $quote->getBillingAddress();

        // Start with the base payment data
        $paymentData = $this->prepareBasePaymentData($quote);

        // Add customer-specific data
        $paymentData['customer'] = $this->getCustomerDetailsByQuote->execute($quote, $email);
        $paymentData['billing_details'] = $this->getAddressDetailsByQuoteAddress->executeBilling($quote->getBillingAddress(), $email);
        $paymentData['shipping_details'] = $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress, $email);

        return $paymentData;
    }
}

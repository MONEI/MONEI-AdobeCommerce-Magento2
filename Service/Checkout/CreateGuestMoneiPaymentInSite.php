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
use Monei\Model\CreatePaymentRequest;
use Monei\Model\PaymentTransactionType;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateGuestMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\AbstractApiService;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Monei create payment in site REST integration service class.
 *
 * Creates a payment for a guest customer by processing their cart information
 * and communicating with the MONEI API.
 */
class CreateGuestMoneiPaymentInSite extends AbstractApiService implements CreateGuestMoneiPaymentInSiteInterface
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
     * Constructor for CreateGuestMoneiPaymentInSite.
     *
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler $exceptionHandler Exception handler for MONEI API errors
     * @param MoneiApiClient $apiClient API client factory for MONEI SDK
     * @param CartRepositoryInterface $quoteRepository Repository for accessing and saving quotes
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId Service to convert masked quote ID to quote ID
     * @param GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote Service to get customer details from a quote
     * @param GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress Service to get address details
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration
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
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->getCustomerDetailsByQuote = $getCustomerDetailsByQuote;
        $this->getAddressDetailsByQuoteAddress = $getAddressDetailsByQuoteAddress;
        $this->moduleConfig = $moduleConfig;
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
    public function execute(string $cartId, string $email): array
    {
        // First, resolve the quote from the masked cart ID
        $quote = $this->resolveQuote($cartId);

        // Reserve order ID to prevent race conditions
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        // Check if the quote already has a payment ID to prevent double payment
        $existingPaymentId = $quote->getData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
        if (!empty($existingPaymentId)) {
            // Return the existing payment ID to avoid creating a duplicate payment
            $this->logger->info("Using existing payment ID", [
                'payment_id' => $existingPaymentId,
                'order_id' => $quote->getReservedOrderId()
            ]);

            // Return a mock result with just the ID
            return [['id' => $existingPaymentId]];
        }

        // Use the executeApiCall to wrap the payment creation process
        return $this->executeApiCall(__METHOD__, function () use ($quote, $email) {
            // Build the payment request from the quote and email
            $paymentRequest = $this->buildPaymentRequest($quote, $email);

            // Create the payment using the standardized SDK call pattern
            $result = $this->executeMoneiSdkCall(
                'createGuestPayment',
                function (MoneiClient $moneiSdk) use ($paymentRequest) {
                    return $moneiSdk->payments->create($paymentRequest);
                },
                [
                    'order_id' => $quote->getReservedOrderId(),
                    'quote_id' => $quote->getId(),
                ]
            );

            // Save payment ID to quote for future reference
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?? '');
            $this->quoteRepository->save($quote);

            return [$result];
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
    private function resolveQuote(string $cartId): Quote
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
     * Build payment request object for MONEI API
     *
     * @param Quote $quote The quote to create payment for
     * @param string $email Customer email
     * @return CreatePaymentRequest The constructed payment request
     */
    private function buildPaymentRequest(Quote $quote, string $email): CreatePaymentRequest
    {
        // Create the base payment request with required fields
        $paymentRequest = new CreatePaymentRequest([
            'amount' => (int) ($quote->getBaseGrandTotal() * 100),  // Convert to cents
            'currency' => $quote->getBaseCurrencyCode(),
            'order_id' => $quote->getReservedOrderId(),
            'complete_url' => $this->moduleConfig->getUrl() . '/monei/payment/complete',
            'callback_url' => $this->moduleConfig->getUrl() . '/monei/payment/callback',
            'cancel_url' => $this->moduleConfig->getUrl() . '/monei/payment/cancel',
            'fail_url' => $this->moduleConfig->getUrl() . '/monei/payment/fail'
        ]);

        // Set transaction type if necessary using the SDK enum
        if (TypeOfPayment::TYPE_PRE_AUTHORIZED === $this->moduleConfig->getTypeOfPayment()) {
            $paymentRequest->setTransactionType(PaymentTransactionType::AUTH);
        }

        // Set customer information
        $paymentRequest->setCustomer(
            $this->getCustomerDetailsByQuote->execute($quote, $email)
        );

        // Set billing details
        $paymentRequest->setBillingDetails(
            $this->getAddressDetailsByQuoteAddress->executeBilling($quote->getBillingAddress(), $email)
        );

        // Set shipping details using billing address as fallback if no shipping address exists
        $shippingAddress = $quote->getShippingAddress() ?: $quote->getBillingAddress();
        $paymentRequest->setShippingDetails(
            $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress, $email)
        );

        // Add metadata about the origin
        $metadata = [
            'magento_module' => 'monei_magento2',
            'payment_type' => 'guest_payment',
            'quote_id' => $quote->getId()
        ];
        $paymentRequest->setMetadata($metadata);

        return $paymentRequest;
    }
}

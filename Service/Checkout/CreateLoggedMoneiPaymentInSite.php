<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\CreatePayment;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

/**
 * Monei create payment REST integration service class for logged-in customers.
 *
 * Handles payment creation for customers who are logged into their accounts,
 * utilizing their stored customer information.
 */
class CreateLoggedMoneiPaymentInSite extends AbstractCheckoutService implements CreateLoggedMoneiPaymentInSiteInterface
{
    /**
     * Service for retrieving address details from quote address.
     *
     * @var GetAddressDetailsByQuoteAddressInterface
     */
    private GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress;

    /**
     * Service for creating Monei payments.
     *
     * @var CreatePayment
     */
    private CreatePayment $createPayment;

    /**
     * Customer details service
     *
     * @var GetCustomerDetailsByQuoteInterface
     */
    private GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote;

    /**
     * Module configuration
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * Constructor for CreateLoggedMoneiPaymentInSite service.
     *
     * @param Logger $logger Logger instance
     * @param ApiExceptionHandler $exceptionHandler API error handling service
     * @param MoneiApiClient $apiClient MONEI API client service
     * @param CartRepositoryInterface $quoteRepository Quote repository
     * @param Session $checkoutSession Checkout session
     * @param GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote Customer details service
     * @param GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress Address details service
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration
     * @param CreatePayment $createPayment Payment creation service
     * @param GetPaymentInterface $getPaymentService Service to retrieve payment details
     */
    public function __construct(
        Logger $logger,
        ApiExceptionHandler $exceptionHandler,
        MoneiApiClient $apiClient,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddress,
        MoneiPaymentModuleConfigInterface $moduleConfig,
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
        $this->moduleConfig = $moduleConfig;
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
    public function execute(string $cartId, string $email)
    {
        // First, resolve the quote from cart ID - use parent class method
        $quote = $this->resolveQuote($cartId);

        // Reserve order ID to prevent race conditions
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        // Check if the quote already has a payment ID to prevent double payment
        $existingPayment = $this->checkExistingPayment($quote);
        if ($existingPayment !== null) {
            return $existingPayment;
        }

        return $this->executeApiCall(__METHOD__, function () use ($quote, $email) {
            // Prepare payment data
            $paymentData = $this->preparePaymentData($quote, $email);

            // Create payment
            $result = $this->createPayment->execute($paymentData);
            $paymentId = $result->getId() ?? '';

            // Save payment ID to quote for future reference
            $this->savePaymentIdToQuote($quote, $paymentId);

            // Return a properly formatted result
            return $this->createPaymentResult($paymentId);
        }, [
            'quote_id' => $quote->getId(),
            'reserved_order_id' => $quote->getReservedOrderId()
        ]);
    }

    /**
     * Prepare payment data from quote
     *
     * @param Quote $quote The quote to prepare payment data from
     * @param string|null $email Optional customer email override
     * @return array Payment data ready for the CreatePayment service
     */
    private function preparePaymentData(Quote $quote, ?string $email = null): array
    {
        // Get shipping address or fallback to billing if shipping is not available
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress || !$shippingAddress->getId()) {
            $shippingAddress = $quote->getBillingAddress();
        }

        // Start with the base payment data
        $paymentData = $this->prepareBasePaymentData($quote);

        // Add customer-specific data
        $paymentData['customer'] = $this->getCustomerDetailsByQuote->execute($quote, $email);
        $paymentData['billing_details'] = $this->getAddressDetailsByQuoteAddress->executeBilling($quote->getBillingAddress());
        $paymentData['shipping_details'] = $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress);

        return $paymentData;
    }
}

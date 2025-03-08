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
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateGuestMoneiPaymentInSiteInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote;
use OpenAPI\Client\Model\CreatePaymentRequest;
use OpenAPI\Client\Model\PaymentTransactionType;
use OpenAPI\Client\ApiException;

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
     * Monei API client.
     *
     * @var MoneiApiClient
     */
    private MoneiApiClient $moneiApiClient;

    /**
     * Module configuration.
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * Constructor for CreateGuestMoneiPaymentInSite.
     *
     * @param CartRepositoryInterface $quoteRepository Repository for accessing and saving quotes
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId Service to convert masked quote ID to quote ID
     * @param GetCustomerDetailsByQuote $getCustomerDetailsByQuote Service to get customer details from a quote
     * @param GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress Service to get address details
     * @param MoneiApiClient $moneiApiClient Monei API client
     * @param MoneiPaymentModuleConfigInterface $moduleConfig Module configuration
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        GetCustomerDetailsByQuote $getCustomerDetailsByQuote,
        GetAddressDetailsByQuoteAddress $getAddressDetailsByQuoteAddress,
        MoneiApiClient $moneiApiClient,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->moneiApiClient = $moneiApiClient;
        $this->moduleConfig = $moduleConfig;
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
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (\Exception $e) {
            throw new LocalizedException(__('An error occurred to retrieve the information about the quote'));
        }

        $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($quoteId);
        if (!$quote) {
            throw new LocalizedException(__('An error occurred to retrieve the information about the quote'));
        }
        // Save the quote in order to avoid that the other processes can reserve the order
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);

        try {
            // Create payment request object directly
            $paymentRequest = new CreatePaymentRequest([
                'amount' => (int)($quote->getBaseGrandTotal() * 100),
                'currency' => $quote->getBaseCurrencyCode(),
                'order_id' => $quote->getReservedOrderId(),
                // Add URLs from our configuration
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

            // Set shipping details
            $shippingAddress = $quote->getShippingAddress() ?: $quote->getBillingAddress();
            $paymentRequest->setShippingDetails(
                $this->getAddressDetailsByQuoteAddress->executeShipping($shippingAddress, $email)
            );

            // Get the SDK client and create the payment directly
            $moneiSdk = $this->moneiApiClient->getMoneiSdk();
            $payment = $moneiSdk->payments->create($paymentRequest);

            // Convert response to array
            $result = $this->moneiApiClient->convertResponseToArray($payment);

            // Save payment ID to quote
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $result['id'] ?: '');
            $this->quoteRepository->save($quote);

            return [$result];
        } catch (ApiException $e) {
            throw new LocalizedException(
                __('An error occurred rendering the pay with card. Please try again later.')
            );
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('An error occurred rendering the pay with card. Please try again later.')
            );
        }
    }
}

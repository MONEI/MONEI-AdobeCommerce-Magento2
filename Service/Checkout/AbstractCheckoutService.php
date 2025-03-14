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
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Api\AbstractApiService;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Abstract base class for checkout payment services
 *
 * Provides common functionality for handling quotes, checkout sessions,
 * and payment creation across different types of checkout flows.
 */
abstract class AbstractCheckoutService extends AbstractApiService
{
    /**
     * Quote repository for managing quote data
     *
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * Checkout session for accessing the current quote
     *
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * Payment retrieval service
     *
     * @var GetPaymentInterface
     */
    protected GetPaymentInterface $getPaymentService;

    /**
     * Base constructor for checkout services
     *
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler|null $exceptionHandler Exception handler for API calls
     * @param MoneiApiClient|null $apiClient MONEI API client
     * @param CartRepositoryInterface $quoteRepository Repository for accessing quotes
     * @param Session $checkoutSession Checkout session for accessing the current quote
     * @param GetPaymentInterface $getPaymentService Service to retrieve payment details
     */
    public function __construct(
        Logger $logger,
        ?ApiExceptionHandler $exceptionHandler,
        ?MoneiApiClient $apiClient,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession,
        GetPaymentInterface $getPaymentService
    ) {
        parent::__construct($logger, $exceptionHandler, $apiClient);
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->getPaymentService = $getPaymentService;
    }

    /**
     * Resolves a quote by cart ID, with optional fallback to checkout session
     *
     * @param string $cartId Cart ID to resolve
     * @param bool $useSessionFirst Whether to try to use the checkout session first
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws LocalizedException
     */
    protected function resolveQuote(string $cartId, bool $useSessionFirst = true): \Magento\Quote\Api\Data\CartInterface
    {
        try {
            $quote = null;

            // Try to get from session first if requested
            if ($useSessionFirst) {
                $quote = $this->checkoutSession->getQuote();
                if ($quote && $quote->getId()) {
                    return $quote;
                }
            }

            // Get from repository as fallback or primary source
            $quote = $this->quoteRepository->get($cartId);

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
     * Checks if a quote already has a payment ID to prevent duplicate payments
     * Only returns existing payment if it has a pending status and the same amount
     *
     * @param Quote $quote The quote to check
     * @return array|null Returns payment data array if valid payment exists, null otherwise
     */
    protected function checkExistingPayment(Quote $quote): ?array
    {
        $existingPaymentId = $quote->getData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
        if (empty($existingPaymentId)) {
            return null;
        }

        try {
            // Get current quote amount in cents
            $currentAmount = (int) ($quote->getBaseGrandTotal() * 100);

            // Retrieve payment details from API
            $payment = $this->getPaymentService->execute($existingPaymentId);

            // Check if payment has pending status and same amount

            $isPending = $payment->getStatus() === Status::PENDING && $payment->getNextAction()->getRedirectUrl() === $isUnconfirmed = $payment->getNextAction()->getType() === 'CONFIRM';
            $isSameAmount = $payment->getAmount() === $currentAmount;

            if ($isPending && $isUnconfirmed && $isSameAmount) {
                // Log the reuse of existing payment ID
                $this->logger->info('Using existing payment ID with pending status and matching amount', [
                    'payment_id' => $existingPaymentId,
                    'order_id' => $quote->getReservedOrderId(),
                    'amount' => $payment->getAmount(),
                    'status' => $payment->getStatus()
                ]);

                // Return an array with the payment ID as expected by the frontend
                return [['id' => $existingPaymentId]];
            } else {
                $this->logger->info('Existing payment cannot be reused - creating new payment', [
                    'payment_id' => $existingPaymentId,
                    'order_id' => $quote->getReservedOrderId(),
                    'pending' => $isPending ? 'yes' : 'no',
                    'amount_match' => $isSameAmount ? 'yes' : 'no',
                    'current_amount' => $currentAmount,
                    'payment_amount' => $payment->getAmount(),
                    'status' => $payment->getStatus()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error checking existing payment: ' . $e->getMessage(), [
                'payment_id' => $existingPaymentId,
                'order_id' => $quote->getReservedOrderId()
            ]);
            // If we can't check the payment details, don't reuse the existing ID
        }

        return null;
    }

    /**
     * Prepares base payment data common to all payment types
     *
     * @param Quote $quote The quote to prepare payment data from
     * @return array Base payment data
     */
    protected function prepareBasePaymentData(Quote $quote): array
    {
        return [
            'amount' => (int) ($quote->getBaseGrandTotal() * 100),  // Convert to cents
            'currency' => $quote->getBaseCurrencyCode(),
            'order_id' => $quote->getReservedOrderId()
        ];
    }

    /**
     * Saves the payment ID to the quote for future reference
     *
     * @param Quote $quote The quote to update
     * @param string $paymentId The payment ID to save
     * @return void
     */
    protected function savePaymentIdToQuote(Quote $quote, string $paymentId): void
    {
        if (!empty($paymentId)) {
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $paymentId);
            $this->quoteRepository->save($quote);
        }
    }

    /**
     * Creates a standardized result array with payment ID for frontend
     *
     * @param string $paymentId The payment ID
     * @param array $additionalData Additional data to include in response
     * @return array Formatted payment result
     */
    protected function createPaymentResult(string $paymentId, array $additionalData = []): array
    {
        $result = ['id' => $paymentId];
        if (!empty($additionalData)) {
            $result = array_merge($result, $additionalData);
        }

        return [$result];
    }
}

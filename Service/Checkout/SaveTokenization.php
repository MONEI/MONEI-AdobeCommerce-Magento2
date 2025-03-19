<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\SaveTokenizationInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service to save the payment tokenization flag on the customer's quote.
 *
 * This service handles saving the customer's preference for storing their payment
 * method details for future purchases (tokenization).
 */
class SaveTokenization extends AbstractCheckoutService implements SaveTokenizationInterface
{
    /**
     * Constructor for SaveTokenization.
     *
     * @param Logger $logger Logger for tracking operations
     * @param ApiExceptionHandler|null $exceptionHandler Exception handler for API calls
     * @param MoneiApiClient|null $apiClient MONEI API client
     * @param CartRepositoryInterface $quoteRepository Repository for managing quote data
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
        parent::__construct(
            $logger,
            $exceptionHandler,
            $apiClient,
            $quoteRepository,
            $checkoutSession,
            $getPaymentService
        );
    }

    /**
     * Save the tokenization flag on the quote.
     *
     * @param string $cartId         The ID of the cart/quote
     * @param int    $isVaultChecked Flag indicating if the customer wants to save the payment method (1 = yes, 0 = no)
     *
     * @throws LocalizedException If there are issues retrieving the quote or saving the data
     *
     * @return array Empty array on success
     */
    public function execute(string $cartId, int $isVaultChecked = 0)
    {
        try {
            // Use the parent class method to resolve the quote
            $quote = $this->resolveQuote($cartId);

            // Save the tokenization flag
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $isVaultChecked);
            $this->quoteRepository->save($quote);

            return [];
        } catch (\Exception $e) {
            $this->logger->error('[Tokenization] Error saving tokenization flag: ' . $e->getMessage(), [
                'cartId' => $cartId,
                'isVaultChecked' => $isVaultChecked
            ]);

            throw new LocalizedException(__('An error occurred trying to save the card.'));
        }
    }
}

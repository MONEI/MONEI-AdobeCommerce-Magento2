<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\SaveTokenizationInterface;

/**
 * Service to save the payment tokenization flag on the customer's quote.
 *
 * This service handles saving the customer's preference for storing their payment
 * method details for future purchases (tokenization).
 */
class SaveTokenization implements SaveTokenizationInterface
{
    /** Quote repository for managing quote data. */
    private CartRepositoryInterface $quoteRepository;

    /** Checkout session to access current quote. */
    private Session $checkoutSession;

    /**
     * Constructor for SaveTokenization.
     *
     * @param CartRepositoryInterface $quoteRepository Repository for managing quote data
     * @param Session                 $checkoutSession Checkout session for accessing the current quote
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
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
    public function execute(string $cartId, int $isVaultChecked = 0): array
    {
        $quote = $this->checkoutSession->getQuote() ?? $this->quoteRepository->get($cartId);
        if (!$quote) {
            throw new LocalizedException(__('An error occurred to retrieve the information about the quote'));
        }

        try {
            $quote->setData(QuoteInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $isVaultChecked);
            $this->quoteRepository->save($quote);

            return [];
        } catch (\Exception $e) {
            throw new LocalizedException(__('An error occurred trying save the card.'));
        }
    }
}

<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\Checkout\SaveTokenizationInterface;

class SaveTokenization implements SaveTokenizationInterface
{
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly Session                 $checkoutSession)
    {
    }

    /**
     * @throws LocalizedException
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
        } catch (\Exception) {
            throw new LocalizedException(__('An error occurred trying save the card.'));
        }
    }
}

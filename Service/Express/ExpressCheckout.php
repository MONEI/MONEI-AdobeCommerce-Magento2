<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Express;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Api\Service\Express\ExpressCheckoutInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Express checkout server-side operations.
 *
 * Every amount returned here is computed from the quote. The client is never
 * trusted with one - it only ever names an address or a shipping option.
 */
class ExpressCheckout implements ExpressCheckoutInterface
{
    /**
     * Result returned when no carrier serves the address the shopper picked.
     */
    public const RESULT_INVALID_SHIPPING_ADDRESS = 'invalid_shipping_address';

    public const RESULT_SUCCESS = 'success';

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param CartRepositoryInterface $quoteRepository Repository for accessing quotes
     * @param Logger                  $logger          Logger for tracking operations
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Logger $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * Shipping options for an address the shopper picked in the wallet sheet.
     *
     * @param string  $cartId
     * @param mixed[] $address Partial address from the wallet
     *
     * @return mixed[]
     */
    public function getShippingOptions(string $cartId, array $address): array
    {
        $quote = $this->loadQuote($cartId);

        if ($quote->isVirtual()) {
            // Nothing to ship, so there is nothing to price. Returning the current
            // total keeps the sheet correct rather than leaving it stale.
            return [
                'result' => self::RESULT_SUCCESS,
                'shippingOptions' => [],
                'amount' => $this->getAmount($quote),
                'currency' => $quote->getBaseCurrencyCode(),
            ];
        }

        $this->applyPartialAddress($quote, $address);

        $options = $this->collectShippingOptions($quote);

        if (empty($options)) {
            return [
                'result' => self::RESULT_INVALID_SHIPPING_ADDRESS,
                'message' => (string) __('No shipping method is available for this address.'),
                'shippingOptions' => [],
                'amount' => $this->getAmount($quote),
                'currency' => $quote->getBaseCurrencyCode(),
            ];
        }

        // The wallet auto-selects the first option, so the amount returned must be
        // the total with that option already applied - otherwise the sheet shows a
        // figure the shopper will never be charged.
        $this->applyShippingMethod($quote, $options[0]['id']);
        $options[0]['selected'] = true;

        return [
            'result' => self::RESULT_SUCCESS,
            'shippingOptions' => $options,
            'amount' => $this->getAmount($quote),
            'currency' => $quote->getBaseCurrencyCode(),
        ];
    }

    /**
     * Apply the shipping option the shopper chose and return the new total.
     *
     * @param string  $cartId
     * @param mixed[] $address
     * @param string  $optionId
     *
     * @return mixed[]
     */
    public function selectShippingOption(string $cartId, array $address, string $optionId): array
    {
        $quote = $this->loadQuote($cartId);

        if (!$quote->isVirtual()) {
            $this->applyPartialAddress($quote, $address);
            $this->applyShippingMethod($quote, $optionId);
        }

        return [
            'result' => self::RESULT_SUCCESS,
            'amount' => $this->getAmount($quote),
            'currency' => $quote->getBaseCurrencyCode(),
        ];
    }

    /**
     * Place the order for an approved wallet payment.
     *
     * @param string  $cartId
     * @param mixed[] $payload
     *
     * @return mixed[]
     */
    public function placeOrder(string $cartId, array $payload): array
    {
        throw new LocalizedException(__('Express order placement is not available yet.'));
    }

    /**
     * Total in minor units, in the store's base currency.
     *
     * Base currency throughout, to match Service/Checkout/AbstractCheckoutService,
     * which creates the payment from getBaseGrandTotal(). Mixing quote currency in
     * here would show one figure and charge another on a multi-currency store.
     *
     * @param Quote $quote
     */
    private function getAmount(Quote $quote): int
    {
        $this->recollectTotals($quote);

        return (int) round((float) $quote->getBaseGrandTotal() * 100);
    }

    /**
     * Recollect totals.
     *
     * collectTotals() returns immediately when the collected flag is set, which it
     * is by the time a webapi request reaches here. Without clearing it first the
     * totals - and so the amount - are whatever they were before the address was
     * applied.
     *
     * @param Quote $quote
     */
    private function recollectTotals(Quote $quote): void
    {
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
    }

    /**
     * Apply the partial address a wallet supplies before approval.
     *
     * Wallets withhold the full address until the shopper approves - typically only
     * country, postcode and city are present. Personal fields are cleared rather
     * than left alone, because a logged-in customer's saved values would otherwise
     * survive alongside the wallet's location data and skew the rates.
     *
     * @param Quote   $quote
     * @param mixed[] $address
     */
    private function applyPartialAddress(Quote $quote, array $address): void
    {
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->addData([
            'country_id' => $address['country'] ?? null,
            'postcode' => $address['postalCode'] ?? ($address['zip'] ?? null),
            'city' => $address['city'] ?? null,
            'region' => $address['state'] ?? ($address['region'] ?? null),
            'firstname' => null,
            'lastname' => null,
            'street' => null,
            'telephone' => null,
            'company' => null,
        ]);

        $shippingAddress->setCollectShippingRates(true);
    }

    /**
     * Collect the carrier rates available for the address currently on the quote.
     *
     * @param Quote $quote
     *
     * @return mixed[]
     */
    private function collectShippingOptions(Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        $options = [];

        foreach ($shippingAddress->getGroupedAllShippingRates() as $rates) {
            foreach ($rates as $rate) {
                $options[] = [
                    // The id must round-trip to setShippingMethod().
                    'id' => $rate->getCode(),
                    'label' => trim(
                        (string) $rate->getCarrierTitle() . ' - ' . (string) $rate->getMethodTitle(),
                        ' -'
                    ),
                    'amount' => (int) round((float) $rate->getPrice() * 100),
                    'type' => 'SHIPPING',
                ];
            }
        }

        return $options;
    }

    /**
     * Apply a shipping method and recollect totals.
     *
     * @param Quote  $quote
     * @param string $optionId
     */
    private function applyShippingMethod(Quote $quote, string $optionId): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingAddress->setShippingMethod($optionId);

        $this->recollectTotals($quote);
    }

    /**
     * Load the quote for a cart id.
     *
     * @param string $cartId
     *
     * @throws LocalizedException
     */
    private function loadQuote(string $cartId): Quote
    {
        try {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->get((int) $cartId);
        } catch (\Exception $e) {
            $this->logger->error('[Express] Quote not found: ' . $e->getMessage(), ['cartId' => $cartId]);

            throw new LocalizedException(__('Your session has expired. Please reload the page.'));
        }

        if (!$quote->getId() || !$quote->hasItems()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }

        return $quote;
    }
}

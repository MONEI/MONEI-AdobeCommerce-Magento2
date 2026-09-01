<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Express;

use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Api\Service\ConfirmPaymentInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Api\Service\Express\ExpressCheckoutInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Quote\SetExpressAddressesOnQuote;

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
     * @var SetExpressAddressesOnQuote
     */
    private SetExpressAddressesOnQuote $setAddresses;

    /**
     * @var GetAddressDetailsByQuoteAddress
     */
    private GetAddressDetailsByQuoteAddress $getAddressDetails;

    /**
     * @var CreatePaymentInterface
     */
    private CreatePaymentInterface $createPayment;

    /**
     * @var ConfirmPaymentInterface
     */
    private ConfirmPaymentInterface $confirmPayment;

    /**
     * @var CartManagementInterface
     */
    private CartManagementInterface $cartManagement;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var EventManager
     */
    private EventManager $eventManager;

    /**
     * @param CartRepositoryInterface         $quoteRepository   Repository for accessing quotes
     * @param Logger                          $logger            Logger for tracking operations
     * @param SetExpressAddressesOnQuote      $setAddresses      Maps wallet addresses onto the quote
     * @param GetAddressDetailsByQuoteAddress $getAddressDetails Maps quote addresses to MONEI shape
     * @param CreatePaymentInterface          $createPayment     Creates the MONEI payment
     * @param ConfirmPaymentInterface         $confirmPayment    Confirms the MONEI payment
     * @param CartManagementInterface         $cartManagement    Places the order from the quote
     * @param Session                         $checkoutSession   Checkout session
     * @param EventManager                    $eventManager      Event dispatcher
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Logger $logger,
        SetExpressAddressesOnQuote $setAddresses,
        GetAddressDetailsByQuoteAddress $getAddressDetails,
        CreatePaymentInterface $createPayment,
        ConfirmPaymentInterface $confirmPayment,
        CartManagementInterface $cartManagement,
        Session $checkoutSession,
        EventManager $eventManager
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->setAddresses = $setAddresses;
        $this->getAddressDetails = $getAddressDetails;
        $this->createPayment = $createPayment;
        $this->confirmPayment = $confirmPayment;
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession;
        $this->eventManager = $eventManager;
    }

    /**
     * Shipping options for an address the shopper picked in the wallet sheet.
     *
     * @param string $address JSON-encoded partial address from the wallet
     *
     * @return mixed[]
     */
    public function getShippingOptions(string $address): string
    {
        return $this->encode($this->buildShippingOptions($address));
    }

    /**
     * @param string $address
     *
     * @return mixed[]
     */
    private function buildShippingOptions(string $address): array
    {
        $address = $this->decode($address);
        $quote = $this->loadQuote();

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
     * @param string $address  JSON-encoded partial address from the wallet
     * @param string $optionId
     *
     * @return mixed[]
     */
    public function selectShippingOption(string $address, string $optionId): string
    {
        return $this->encode($this->buildSelectShippingOption($address, $optionId));
    }

    /**
     * @param string $address
     * @param string $optionId
     *
     * @return mixed[]
     */
    private function buildSelectShippingOption(string $address, string $optionId): array
    {
        $address = $this->decode($address);
        $quote = $this->loadQuote();

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
     * @param string $payload JSON-encoded wallet payload
     *
     * @return mixed[]
     */
    public function placeOrder(string $payload): string
    {
        return $this->encode($this->buildPlaceOrder($payload));
    }

    /**
     * @param string $payload
     *
     * @return mixed[]
     */
    private function buildPlaceOrder(string $payload): array
    {
        $payload = $this->decode($payload);
        $quote = $this->loadQuote();

        $token = (string) ($payload['token'] ?? '');
        if ('' === $token) {
            throw new LocalizedException(__('The wallet did not return a payment token.'));
        }

        $billing = (array) ($payload['billingDetails'] ?? []);
        $shipping = (array) ($payload['shippingDetails'] ?? []);

        // Express has no form for a guest to type an email into, so the wallet is the
        // only source of one. Without this the failure surfaces from the MONEI API as
        // an invalid customer email, which reads as a MONEI fault rather than a
        // missing field.
        $email = (string) ($billing['email'] ?? ($shipping['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(
                __('The wallet did not return an email address, which is required to place the order.')
            );
        }

        $this->setAddresses->execute($quote, $billing, $shipping);
        $quote->setCustomerEmail($email);

        if (!$quote->isVirtual()) {
            $optionId = (string) ($payload['shippingOption']['id'] ?? '');
            if ('' === $optionId) {
                throw new LocalizedException(__('Please select a shipping method.'));
            }
            $this->applyShippingMethod($quote, $optionId);
        }

        $this->recollectTotals($quote);
        $amount = (int) round((float) $quote->getBaseGrandTotal() * 100);

        // finalAmount is never the charged amount, but a mismatch means the shopper
        // approved a different figure than we are about to take.
        $this->assertAmountMatchesWhatTheWalletShowed($payload, $amount);

        // Validate the address before anything is created. A country with extra
        // required fields would otherwise leave a reserved order id and a created
        // payment to unwind.
        $this->assertAddressesAreSubmittable($quote);

        $quote->reserveOrderId();

        $shippingDetails = $this->getAddressDetails->execute(
            $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress(),
            $email
        );

        $payment = $this->createPayment->execute([
            'amount' => $amount,
            'currency' => (string) $quote->getBaseCurrencyCode(),
            'order_id' => (string) $quote->getReservedOrderId(),
            'shipping_details' => $shippingDetails,
        ]);

        $paymentId = (string) $payment->getId();
        $quote->setData('monei_payment_id', $paymentId);

        $this->prepareCheckoutMethod($quote, $email);

        $quote->getPayment()->importData([
            'method' => Monei::EXPRESS_CODE,
            'additional_data' => ['monei_payment_id' => $paymentId],
        ]);

        $this->quoteRepository->save($quote);

        $order = $this->cartManagement->submit($quote);

        if (!$order) {
            throw new LocalizedException(__('Could not place the order. Please try again.'));
        }

        // fieldset.xml does not copy the payment id, and the webhook that normally
        // writes it never fires for a payment that was never confirmed. Without this
        // a confirm failure leaves an order nothing can reconcile.
        $order->setData('monei_payment_id', $paymentId);
        $order->getPayment()->setAdditionalInformation('monei_payment_id', $paymentId);

        $this->eventManager->dispatch(
            'checkout_type_onepage_save_order_after',
            ['order' => $order, 'quote' => $quote]
        );
        $this->eventManager->dispatch(
            'checkout_submit_all_after',
            ['order' => $order, 'quote' => $quote]
        );

        $confirmed = $this->confirmPayment->execute([
            'payment_id' => $paymentId,
            'payment_token' => $token,
            'billing_details' => $this->getAddressDetails->execute($quote->getBillingAddress(), $email),
            'shipping_details' => $shippingDetails,
        ]);

        // Only now is the order paid for. Setting these earlier would let a shopper
        // reach a success page for an order whose confirm failed.
        $this
            ->checkoutSession
            ->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        $nextAction = $confirmed->getNextAction();

        return [
            'result' => self::RESULT_SUCCESS,
            'orderId' => $order->getIncrementId(),
            'paymentId' => $paymentId,
            'redirectUrl' => $nextAction ? (string) $nextAction->getRedirectUrl() : null,
        ];
    }

    /**
     * Refuse the order when the wallet's figure and the recomputed total disagree.
     *
     * @param mixed[] $payload
     * @param int     $amount
     *
     * @throws LocalizedException
     */
    private function assertAmountMatchesWhatTheWalletShowed(array $payload, int $amount): void
    {
        $walletAmount = $payload['finalAmount'] ?? null;

        if (null === $walletAmount) {
            return;
        }

        if ((int) $walletAmount !== $amount) {
            $this->logger->error(
                '[Express] Refused an order: the wallet reported a different total.',
                ['walletAmount' => (int) $walletAmount, 'recomputedAmount' => $amount]
            );

            throw new LocalizedException(
                __('The order total changed while you were paying. Please try again.')
            );
        }
    }

    /**
     * Check the addresses will survive Magento's own validation.
     *
     * Runs before the payment is created so a store with extra required fields for
     * a country fails with nothing to unwind. PrestaShop hit this with Spain, whose
     * national ID no wallet supplies.
     *
     * @param Quote $quote
     *
     * @throws LocalizedException
     */
    private function assertAddressesAreSubmittable(Quote $quote): void
    {
        $addresses = [$quote->getBillingAddress()];

        if (!$quote->isVirtual()) {
            $addresses[] = $quote->getShippingAddress();
        }

        foreach ($addresses as $address) {
            $errors = $address->validate();

            if (true !== $errors) {
                $messages = is_array($errors) ? array_map('strval', $errors) : [(string) $errors];
                $this->logger->error(
                    '[Express] Address rejected before payment creation.',
                    ['errors' => $messages]
                );

                throw new LocalizedException(
                    __(
                        'This address cannot be used for express checkout: %1',
                        implode(' ', $messages)
                    )
                );
            }
        }
    }

    /**
     * Set the checkout method and, for a guest, everything submit() needs.
     *
     * setCheckoutMethod alone is not enough: it is only read by placeOrder(), which
     * express bypasses by calling submit() directly.
     *
     * @param Quote  $quote
     * @param string $email
     */
    private function prepareCheckoutMethod(Quote $quote, string $email): void
    {
        if ($quote->getCustomerId()) {
            $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);

            return;
        }

        $quote
            ->setCheckoutMethod(Onepage::METHOD_GUEST)
            ->setCustomerId(null)
            ->setCustomerEmail($email)
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
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
     * Decode a JSON payload from the client.
     *
     * Payloads arrive as strings because Magento's webapi TypeProcessor cannot
     * describe a nested array parameter: it resolves one to anyType and then calls
     * settype() with that, which throws.
     *
     * @param string $json
     *
     * @return mixed[]
     *
     * @throws LocalizedException
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new LocalizedException(__('The payment details could not be read. Please try again.'));
        }

        return $decoded;
    }

    /**
     * Encode a result for the wire.
     *
     * Returned as a string for the same reason payloads arrive as one: an untyped
     * array return is serialised positionally by the webapi layer, which drops the
     * keys the client reads.
     *
     * @param mixed[] $result
     */
    private function encode(array $result): string
    {
        return (string) json_encode($result);
    }

    /**
     * Load the shopper's own quote from the session.
     *
     * Never from a request parameter: these routes are anonymous, so accepting a
     * cart id would let a caller address any cart in the store.
     *
     * @throws LocalizedException
     */
    private function loadQuote(): Quote
    {
        try {
            /** @var Quote $quote */
            $quote = $this->checkoutSession->getQuote();
        } catch (\Exception $e) {
            $this->logger->error('[Express] Quote not available: ' . $e->getMessage());

            throw new LocalizedException(__('Your session has expired. Please reload the page.'));
        }

        if (!$quote->getId() || !$quote->hasItems()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }

        return $quote;
    }
}

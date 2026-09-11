<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Express;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Monei\MoneiPayment\Api\Config\MoneiExpressCheckoutConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Rate;
use Monei\MoneiPayment\Api\Service\ConfirmPaymentInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Service\Express\ExpressCheckout;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use Monei\MoneiPayment\Service\Quote\SetExpressAddressesOnQuote;
use PHPUnit\Framework\TestCase;

/**
 * Test for the express checkout service.
 */
class ExpressCheckoutTest extends TestCase
{
    /**
     * @var ExpressCheckout
     */
    private $_service;

    /**
     * @var CartRepositoryInterface
     */
    private $_quoteRepositoryMock;

    /**
     * @var Session
     */
    private $_sessionMock;

    /**
     * @var MoneiExpressCheckoutConfigInterface
     */
    private $_expressConfigMock;

    protected function setUp(): void
    {
        $this->_quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->_sessionMock = $this->createMock(Session::class);
        $this->_expressConfigMock = $this->createMock(MoneiExpressCheckoutConfigInterface::class);
        $this->_service = new ExpressCheckout(
            $this->_quoteRepositoryMock,
            $this->createMock(Logger::class),
            $this->createMock(SetExpressAddressesOnQuote::class),
            $this->createMock(GetAddressDetailsByQuoteAddress::class),
            $this->createMock(CreatePaymentInterface::class),
            $this->createMock(ConfirmPaymentInterface::class),
            $this->createMock(CartManagementInterface::class),
            $this->_sessionMock,
            $this->createMock(EventManager::class),
            $this->_expressConfigMock
        );
    }

    /**
     * Build a quote whose shipping address offers the given rates.
     *
     * @param array $rates      Each ['code' => string, 'price' => float]
     * @param float $grandTotal Base grand total the quote recollects to
     * @param bool  $isVirtual
     */
    private function makeQuote(array $rates, float $grandTotal, bool $isVirtual = false): Quote
    {
        $rateObjects = [];
        foreach ($rates as $r) {
            // Rate exposes these through DataObject's magic getters, so they must be
            // declared with addMethods() rather than onlyMethods().
            $rate = $this
                ->getMockBuilder(Rate::class)
                ->disableOriginalConstructor()
                ->addMethods(['getCode', 'getCarrierTitle', 'getMethodTitle', 'getPrice'])
                ->getMock();
            $rate->method('getCode')->willReturn($r['code']);
            $rate->method('getCarrierTitle')->willReturn('Carrier');
            $rate->method('getMethodTitle')->willReturn($r['code']);
            $rate->method('getPrice')->willReturn($r['price']);
            $rateObjects[] = $rate;
        }

        // setCollectShippingRates and setShippingMethod are DataObject magic setters;
        // the rest are declared on Address itself.
        $address = $this
            ->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCollectShippingRates', 'setShippingMethod'])
            ->onlyMethods(['addData', 'collectShippingRates', 'getGroupedAllShippingRates'])
            ->getMock();
        $address->method('addData')->willReturnSelf();
        $address->method('setCollectShippingRates')->willReturnSelf();
        $address->method('collectShippingRates')->willReturnSelf();
        $address->method('setShippingMethod')->willReturnSelf();
        $address->method('getGroupedAllShippingRates')->willReturn($rateObjects ? [$rateObjects] : []);

        // setTotalsCollectedFlag, getBaseGrandTotal and getBaseCurrencyCode reach
        // Quote through DataObject's magic accessors.
        $quote = $this
            ->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['setTotalsCollectedFlag', 'getBaseGrandTotal', 'getBaseCurrencyCode'])
            ->onlyMethods(['getId', 'hasItems', 'isVirtual', 'getShippingAddress', 'collectTotals'])
            ->getMock();
        $quote->method('getId')->willReturn(1);
        $quote->method('hasItems')->willReturn(true);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('setTotalsCollectedFlag')->willReturnSelf();
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getBaseGrandTotal')->willReturn($grandTotal);
        $quote->method('getBaseCurrencyCode')->willReturn('EUR');

        return $quote;
    }

    /**
     * The wallet auto-selects the first option, so the amount returned must already
     * include it. Returning a pre-shipping figure here would show the shopper a
     * total they will never be charged.
     *
     * @return void
     */
    public function testGetShippingOptionsAppliesTheFirstOptionToTheReturnedAmount(): void
    {
        $quote = $this->makeQuote(
            [['code' => 'flatrate_flatrate', 'price' => 5.0], ['code' => 'freeshipping_freeshipping', 'price' => 0.0]],
            25.00
        );
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $result = json_decode($this->_service->getShippingOptions(json_encode(['country' => 'ES', 'postalCode' => '28013'])), true);

        $this->assertSame(ExpressCheckout::RESULT_SUCCESS, $result['result']);
        $this->assertCount(2, $result['shippingOptions']);
        $this->assertTrue($result['shippingOptions'][0]['selected']);
        $this->assertSame(2500, $result['amount']);
        $this->assertSame('EUR', $result['currency']);
    }

    /**
     * The option id has to round-trip to setShippingMethod, so it must be the
     * carrier rate code rather than a label or an index.
     *
     * @return void
     */
    public function testShippingOptionIdIsTheCarrierRateCode(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $result = json_decode($this->_service->getShippingOptions(json_encode(['country' => 'ES'])), true);

        $this->assertSame('flatrate_flatrate', $result['shippingOptions'][0]['id']);
        $this->assertSame(500, $result['shippingOptions'][0]['amount']);
    }

    /**
     * An address no carrier serves must be rejected distinctly, so the wallet can
     * refuse it rather than display a total the server will not honour.
     *
     * @return void
     */
    public function testGetShippingOptionsRejectsAnUnservedAddress(): void
    {
        $quote = $this->makeQuote([], 25.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $result = json_decode($this->_service->getShippingOptions(json_encode(['country' => 'AQ'])), true);

        $this->assertSame(ExpressCheckout::RESULT_INVALID_SHIPPING_ADDRESS, $result['result']);
        $this->assertSame([], $result['shippingOptions']);
        $this->assertNotEmpty($result['message']);
    }

    /**
     * A virtual cart has nothing to ship. It must still return the current total,
     * or the sheet keeps whatever figure it opened with.
     *
     * @return void
     */
    public function testVirtualCartReturnsNoOptionsButKeepsTheTotalCurrent(): void
    {
        $quote = $this->makeQuote([], 12.34, true);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $result = json_decode($this->_service->getShippingOptions(json_encode(['country' => 'ES'])), true);

        $this->assertSame(ExpressCheckout::RESULT_SUCCESS, $result['result']);
        $this->assertSame([], $result['shippingOptions']);
        $this->assertSame(1234, $result['amount']);
    }

    /**
     * Test selectShippingOption returns the recalculated amount
     *
     * @return void
     */
    public function testSelectShippingOptionReturnsRecalculatedAmount(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $result = json_decode($this->_service->selectShippingOption(json_encode(['country' => 'ES']), 'flatrate_flatrate'), true);

        $this->assertSame(ExpressCheckout::RESULT_SUCCESS, $result['result']);
        $this->assertSame(3000, $result['amount']);
    }

    /**
     * An empty cart must fail with a message a shopper can act on, rather than
     * surfacing a framework error from further down.
     *
     * @return void
     */
    public function testEmptyCartIsRejected(): void
    {
        $quote = $this
            ->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'hasItems'])
            ->getMock();
        $quote->method('getId')->willReturn(1);
        $quote->method('hasItems')->willReturn(false);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your cart is empty.');

        $this->_service->getShippingOptions(json_encode(['country' => 'ES']));
    }

    /**
     * A missing quote must not leak a repository exception to the shopper.
     *
     * @return void
     */
    public function testMissingQuoteIsReportedAsAnExpiredSession(): void
    {
        $this->_sessionMock->method('getQuote')->willThrowException(new \Exception('no session'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your session has expired. Please reload the page.');

        $this->_service->getShippingOptions(json_encode(['country' => 'ES']));
    }

    /**
     * A payload with no token cannot pay for anything, and must say so rather than
     * failing later inside the MONEI API.
     *
     * @return void
     */
    public function testPlaceOrderRequiresAToken(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The wallet did not return a payment token.');

        $this->_service->placeOrder(json_encode(['billingDetails' => ['email' => 'a@b.com']]));
    }

    /**
     * A PayPal token places a PayPal order, which the merchant may not have
     * switched on for express. The client's word is not enough; the server
     * refuses before it touches the quote.
     *
     * @return void
     */
    public function testPlaceOrderRefusesAPayPalTokenWhenPayPalExpressIsOff(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);
        $this->_expressConfigMock->method('isPayPalEnabled')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('PayPal express checkout is not enabled.');

        $this->_service->placeOrder(json_encode([
            'token' => 'tok_123',
            'paymentMethod' => 'paypal',
            'billingDetails' => ['email' => 'a@b.com'],
        ]));
    }

    /**
     * Express has no form for a guest to type an email into, so the wallet is the
     * only source. Missing it must name the field rather than surfacing MONEI's
     * "Invalid email address" as though the fault were theirs.
     *
     * @return void
     */
    public function testPlaceOrderRequiresAnEmailFromTheWallet(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('did not return an email address');

        $this->_service->placeOrder(json_encode(['token' => 'tok_123', 'billingDetails' => []]));
    }

    /**
     * The wallet's own figure is never charged, but if it disagrees with the
     * recomputed total the shopper approved something else - so refuse rather than
     * silently taking the different amount.
     *
     * @return void
     */
    public function testPlaceOrderRefusesWhenTheWalletTotalDisagrees(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The order total changed while you were paying.');

        $this->_service->placeOrder(json_encode([
            'token' => 'tok_123',
            'billingDetails' => ['email' => 'a@b.com'],
            'shippingDetails' => ['email' => 'a@b.com'],
            'shippingOption' => ['id' => 'flatrate_flatrate'],
            // Quote recomputes to 3000; the wallet claims something else.
            'finalAmount' => 2500,
        ]));
    }

    /**
     * A physical cart with no chosen shipping option would otherwise be submitted
     * without a shipping method and fail deep inside Magento.
     *
     * @return void
     */
    public function testPlaceOrderRequiresAShippingOptionOnAPhysicalCart(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please select a shipping method.');

        $this->_service->placeOrder(json_encode([
            'token' => 'tok_123',
            'billingDetails' => ['email' => 'a@b.com'],
        ]));
    }

    /**
     * An option change carries no address. Applying an empty one would null the
     * country and postcode already on the quote, and rates collected against
     * that reject the very option the shopper chose.
     *
     * @return void
     */
    public function testSelectShippingOptionWithNoAddressLeavesTheQuoteAddressAlone(): void
    {
        $quote = $this->makeQuote([['code' => 'flatrate_flatrate', 'price' => 5.0]], 30.00);
        $this->_sessionMock->method('getQuote')->willReturn($quote);
        $quote->getShippingAddress()->expects($this->never())->method('addData');

        $result = json_decode($this->_service->selectShippingOption('{}', 'flatrate_flatrate'), true);

        $this->assertSame(ExpressCheckout::RESULT_SUCCESS, $result['result']);
    }
}

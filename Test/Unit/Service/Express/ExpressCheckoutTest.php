<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Express;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Rate;
use Monei\MoneiPayment\Service\Express\ExpressCheckout;
use Monei\MoneiPayment\Service\Logger;
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

    protected function setUp(): void
    {
        $this->_quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->_service = new ExpressCheckout(
            $this->_quoteRepositoryMock,
            $this->createMock(Logger::class)
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
        $this->_quoteRepositoryMock->method('get')->willReturn($quote);

        $result = $this->_service->getShippingOptions('1', ['country' => 'ES', 'postalCode' => '28013']);

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
        $this->_quoteRepositoryMock->method('get')->willReturn($quote);

        $result = $this->_service->getShippingOptions('1', ['country' => 'ES']);

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
        $this->_quoteRepositoryMock->method('get')->willReturn($quote);

        $result = $this->_service->getShippingOptions('1', ['country' => 'AQ']);

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
        $this->_quoteRepositoryMock->method('get')->willReturn($quote);

        $result = $this->_service->getShippingOptions('1', ['country' => 'ES']);

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
        $this->_quoteRepositoryMock->method('get')->willReturn($quote);

        $result = $this->_service->selectShippingOption('1', ['country' => 'ES'], 'flatrate_flatrate');

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
        $this->_quoteRepositoryMock->method('get')->willReturn($quote);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your cart is empty.');

        $this->_service->getShippingOptions('1', ['country' => 'ES']);
    }

    /**
     * A missing quote must not leak a repository exception to the shopper.
     *
     * @return void
     */
    public function testMissingQuoteIsReportedAsAnExpiredSession(): void
    {
        $this->_quoteRepositoryMock->method('get')->willThrowException(new \Exception('no such entity'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your session has expired. Please reload the page.');

        $this->_service->getShippingOptions('999', ['country' => 'ES']);
    }
}

<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Monei\MoneiPayment\Service\Quote\SetExpressAddressesOnQuote;
use PHPUnit\Framework\TestCase;

/**
 * Test for SetExpressAddressesOnQuote.
 */
class SetExpressAddressesOnQuoteTest extends TestCase
{
    /**
     * @var SetExpressAddressesOnQuote
     */
    private $_service;

    /**
     * Data captured from addData() on each address.
     *
     * @var array
     */
    private array $_captured = [];

    protected function setUp(): void
    {
        $this->_service = new SetExpressAddressesOnQuote();
        $this->_captured = [];
    }

    private function makeQuote(bool $isVirtual = false): Quote
    {
        $mkAddress = function (string $key) {
            $address = $this
                ->getMockBuilder(Address::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['addData'])
                ->getMock();
            $address
                ->method('addData')
                ->willReturnCallback(function ($data) use ($key, $address) {
                    $this->_captured[$key] = $data;

                    return $address;
                });

            return $address;
        };

        $quote = $this
            ->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCustomerFirstname', 'setCustomerLastname'])
            ->onlyMethods(['getBillingAddress', 'getShippingAddress', 'isVirtual'])
            ->getMock();
        $quote->method('getBillingAddress')->willReturn($mkAddress('billing'));
        $quote->method('getShippingAddress')->willReturn($mkAddress('shipping'));
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('setCustomerFirstname')->willReturnSelf();
        $quote->method('setCustomerLastname')->willReturnSelf();

        return $quote;
    }

    private function walletAddress(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '600000000',
            'address' => [
                'country' => 'ES',
                'city' => 'Madrid',
                'line1' => 'Calle Gran Via 1',
                'zip' => '28013',
                'state' => 'Madrid',
            ],
        ], $overrides);
    }

    /**
     * Test a full wallet address maps onto both quote addresses
     *
     * @return void
     */
    public function testMapsAFullWalletAddress(): void
    {
        $quote = $this->makeQuote();

        $this->_service->execute($quote, $this->walletAddress(), $this->walletAddress());

        $this->assertSame('Ada', $this->_captured['billing']['firstname']);
        $this->assertSame('Lovelace', $this->_captured['billing']['lastname']);
        $this->assertSame('ES', $this->_captured['billing']['country_id']);
        $this->assertSame(['Calle Gran Via 1'], $this->_captured['billing']['street']);
        $this->assertSame('28013', $this->_captured['billing']['postcode']);
        $this->assertArrayHasKey('shipping', $this->_captured);
    }

    /**
     * Magento validates telephone as required on both addresses, and some wallets
     * never return one. Without a placeholder the quote cannot be submitted at all.
     *
     * @return void
     */
    public function testSubstitutesAPlaceholderTelephoneWhenTheWalletOmitsIt(): void
    {
        $quote = $this->makeQuote();
        $address = $this->walletAddress(['phone' => '']);

        $this->_service->execute($quote, $address, $address);

        $this->assertNotEmpty($this->_captured['billing']['telephone']);
    }

    /**
     * Magento requires both name fields. A wallet returning a single word, or
     * nothing, must still produce a submittable quote.
     *
     * @return void
     */
    public function testSingleWordAndEmptyNamesStillFillBothNameFields(): void
    {
        $quote = $this->makeQuote();
        $this->_service->execute($quote, $this->walletAddress(['name' => 'Prince']), $this->walletAddress());
        $this->assertSame('Prince', $this->_captured['billing']['firstname']);
        $this->assertNotSame('', $this->_captured['billing']['lastname']);

        $this->_captured = [];
        $quote2 = $this->makeQuote();
        $this->_service->execute($quote2, $this->walletAddress(['name' => '']), $this->walletAddress());
        $this->assertNotSame('', $this->_captured['billing']['firstname']);
        $this->assertNotSame('', $this->_captured['billing']['lastname']);
    }

    /**
     * A multi-word name keeps everything but the final token as the first name,
     * which is the least-wrong split for the Spanish two-surname convention.
     *
     * @return void
     */
    public function testMultiWordNameKeepsTheFinalTokenAsLastName(): void
    {
        $quote = $this->makeQuote();
        $this->_service->execute($quote, $this->walletAddress(['name' => 'Ana Maria Garcia']), $this->walletAddress());

        $this->assertSame('Ana Maria', $this->_captured['billing']['firstname']);
        $this->assertSame('Garcia', $this->_captured['billing']['lastname']);
    }

    /**
     * PayPal balance payments return no billing address. Falling back to shipping
     * keeps the order placeable rather than failing on a field the wallet will
     * never supply.
     *
     * @return void
     */
    public function testBillingFallsBackToShippingWhenAbsent(): void
    {
        $quote = $this->makeQuote();

        $this->_service->execute($quote, [], $this->walletAddress());

        $this->assertSame('ES', $this->_captured['billing']['country_id']);
    }

    /**
     * A virtual cart has no shipping address to set.
     *
     * @return void
     */
    public function testVirtualQuoteGetsNoShippingAddress(): void
    {
        $quote = $this->makeQuote(true);

        $this->_service->execute($quote, $this->walletAddress(), $this->walletAddress());

        $this->assertArrayHasKey('billing', $this->_captured);
        $this->assertArrayNotHasKey('shipping', $this->_captured);
    }

    /**
     * With no usable address on either side the order must be refused with a
     * message naming the actual problem, not a downstream validation error.
     *
     * @return void
     */
    public function testRefusesWhenNeitherSideHasACountry(): void
    {
        $quote = $this->makeQuote();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The wallet did not return an address');

        $this->_service->execute($quote, [], []);
    }
}

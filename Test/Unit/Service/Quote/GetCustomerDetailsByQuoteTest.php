<?php declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Quote;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monei\MoneiPayment\Service\Quote\GetCustomerDetailsByQuote
 */
class GetCustomerDetailsByQuoteTest extends TestCase
{
    /**
     * @var GetCustomerDetailsByQuote
     */
    private GetCustomerDetailsByQuote $getCustomerDetailsByQuote;

    /**
     * @var CartInterface|MockObject
     */
    private $quoteMock;

    /**
     * @var AddressInterface|MockObject
     */
    private $addressMock;

    protected function setUp(): void
    {
        $this->quoteMock = $this
            ->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBillingAddress', 'getShippingAddress', 'getEntityId'])
            ->addMethods(['getCustomerEmail', 'getCustomerFirstname', 'getCustomerLastname'])
            ->getMock();

        $this->addressMock = $this
            ->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFirstname', 'getLastname', 'getTelephone', 'getEmail'])
            ->getMock();

        $this->getCustomerDetailsByQuote = new GetCustomerDetailsByQuote();
    }

    public function testExecuteWithCustomerData(): void
    {
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getEntityId')
            ->willReturn(1);
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerEmail')
            ->willReturn('customer@example.com');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerFirstname')
            ->willReturn('John');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerLastname')
            ->willReturn('Doe');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($this->addressMock);

        $this
            ->addressMock
            ->expects($this->once())
            ->method('getTelephone')
            ->willReturn('123456789');

        $result = $this->getCustomerDetailsByQuote->execute($this->quoteMock);

        $this->assertEquals([
            'email' => 'customer@example.com',
            'name' => 'John Doe',
            'phone' => '123456789'
        ], $result);
    }

    public function testExecuteWithAddressDataOnly(): void
    {
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getEntityId')
            ->willReturn(1);
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerEmail')
            ->willReturn(null);
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerFirstname')
            ->willReturn('');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerLastname')
            ->willReturn('');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($this->addressMock);

        $this
            ->addressMock
            ->expects($this->once())
            ->method('getEmail')
            ->willReturn('address@example.com');
        $this
            ->addressMock
            ->expects($this->once())
            ->method('getFirstname')
            ->willReturn('Jane');
        $this
            ->addressMock
            ->expects($this->once())
            ->method('getLastname')
            ->willReturn('Smith');
        $this
            ->addressMock
            ->expects($this->once())
            ->method('getTelephone')
            ->willReturn('987654321');

        $result = $this->getCustomerDetailsByQuote->execute($this->quoteMock);

        $this->assertEquals([
            'email' => 'address@example.com',
            'name' => 'Jane Smith',
            'phone' => '987654321'
        ], $result);
    }

    public function testExecuteWithNoData(): void
    {
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getEntityId')
            ->willReturn(null);

        $result = $this->getCustomerDetailsByQuote->execute($this->quoteMock);

        $this->assertEquals([], $result);
    }

    public function testExecuteWithCustomEmail(): void
    {
        $customEmail = 'custom@example.com';

        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getEntityId')
            ->willReturn(1);
        $this
            ->quoteMock
            ->expects($this->never())
            ->method('getCustomerEmail');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerFirstname')
            ->willReturn('John');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerLastname')
            ->willReturn('Doe');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($this->addressMock);

        $this
            ->addressMock
            ->expects($this->once())
            ->method('getTelephone')
            ->willReturn('123456789');

        $result = $this->getCustomerDetailsByQuote->execute($this->quoteMock, $customEmail);

        $this->assertEquals([
            'email' => $customEmail,
            'name' => 'John Doe',
            'phone' => '123456789'
        ], $result);
    }

    public function testExecuteWithBillingAddressFallback(): void
    {
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getEntityId')
            ->willReturn(1);
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerEmail')
            ->willReturn(null);
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerFirstname')
            ->willReturn('');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getCustomerLastname')
            ->willReturn('');
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);
        $this
            ->quoteMock
            ->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($this->addressMock);

        $this
            ->addressMock
            ->expects($this->once())
            ->method('getEmail')
            ->willReturn('billing@example.com');
        $this
            ->addressMock
            ->expects($this->once())
            ->method('getFirstname')
            ->willReturn('Bill');
        $this
            ->addressMock
            ->expects($this->once())
            ->method('getLastname')
            ->willReturn('Pay');
        $this
            ->addressMock
            ->expects($this->once())
            ->method('getTelephone')
            ->willReturn('555666777');

        $result = $this->getCustomerDetailsByQuote->execute($this->quoteMock);

        $this->assertEquals([
            'email' => 'billing@example.com',
            'name' => 'Bill Pay',
            'phone' => '555666777'
        ], $result);
    }
}

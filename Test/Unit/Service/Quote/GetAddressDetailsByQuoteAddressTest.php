<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Quote;

use Magento\Quote\Api\Data\AddressInterface;
use Monei\MoneiPayment\Service\Quote\GetAddressDetailsByQuoteAddress;
use PHPUnit\Framework\TestCase;

class GetAddressDetailsByQuoteAddressTest extends TestCase
{
    /**
     * @var GetAddressDetailsByQuoteAddress
     */
    private $addressDetailsService;

    protected function setUp(): void
    {
        $this->addressDetailsService = new GetAddressDetailsByQuoteAddress();
    }

    /**
     * Test execute method with a complete address
     */
    public function testExecuteWithCompleteAddress(): void
    {
        // Create mock for address
        $addressMock = $this->createMock(AddressInterface::class);

        // Configure address mock to return complete details
        $addressMock->method('getId')->willReturn(1);
        $addressMock->method('getFirstname')->willReturn('John');
        $addressMock->method('getLastname')->willReturn('Doe');
        $addressMock->method('getEmail')->willReturn('john.doe@example.com');
        $addressMock->method('getTelephone')->willReturn('123456789');
        $addressMock->method('getCompany')->willReturn('ACME Inc.');
        $addressMock->method('getCountryId')->willReturn('ES');
        $addressMock->method('getCity')->willReturn('Barcelona');
        $addressMock->method('getStreet')->willReturn(['Calle Principal 42', 'Piso 2']);
        $addressMock->method('getPostcode')->willReturn('08001');
        $addressMock->method('getRegion')->willReturn('Catalonia');

        // Call the execute method
        $result = $this->addressDetailsService->execute($addressMock);

        // Assert all fields are present in the result
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('123456789', $result['phone']);
        $this->assertEquals('ACME Inc.', $result['company']);
        $this->assertEquals('ES', $result['address']['country']);
        $this->assertEquals('Barcelona', $result['address']['city']);
        $this->assertEquals('Calle Principal 42', $result['address']['line1']);
        $this->assertEquals('Piso 2', $result['address']['line2']);
        $this->assertEquals('08001', $result['address']['zip']);
        $this->assertEquals('Catalonia', $result['address']['state']);
    }

    /**
     * Test execute method with minimum required fields
     */
    public function testExecuteWithMinimumFields(): void
    {
        // Create mock for address
        $addressMock = $this->createMock(AddressInterface::class);

        // Configure address mock with only required fields
        $addressMock->method('getId')->willReturn(1);
        $addressMock->method('getFirstname')->willReturn('John');
        $addressMock->method('getLastname')->willReturn('Doe');
        $addressMock->method('getEmail')->willReturn('john.doe@example.com');
        $addressMock->method('getTelephone')->willReturn(null);
        $addressMock->method('getCompany')->willReturn('');
        $addressMock->method('getCountryId')->willReturn('ES');
        $addressMock->method('getCity')->willReturn('Barcelona');
        $addressMock->method('getStreet')->willReturn(['Calle Principal 42']);
        $addressMock->method('getPostcode')->willReturn('08001');
        $addressMock->method('getRegion')->willReturn(null);

        // Call the execute method
        $result = $this->addressDetailsService->execute($addressMock);

        // Assert only the provided fields are in the result
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertArrayHasKey('phone', $result);
        $this->assertArrayNotHasKey('company', $result);
        $this->assertEquals('ES', $result['address']['country']);
        $this->assertEquals('Barcelona', $result['address']['city']);
        $this->assertEquals('Calle Principal 42', $result['address']['line1']);
        $this->assertArrayNotHasKey('line2', $result['address']);
        $this->assertEquals('08001', $result['address']['zip']);
        $this->assertNull($result['address']['state']);
    }

    /**
     * Test execute method with an empty address
     */
    public function testExecuteWithEmptyAddress(): void
    {
        // Create mock for address
        $addressMock = $this->createMock(AddressInterface::class);

        // Configure address mock to return empty/null values
        $addressMock->method('getId')->willReturn(0);
        $addressMock->method('getFirstname')->willReturn(null);
        $addressMock->method('getLastname')->willReturn(null);
        $addressMock->method('getEmail')->willReturn(null);
        $addressMock->method('getTelephone')->willReturn(null);
        $addressMock->method('getCompany')->willReturn(null);
        $addressMock->method('getCountryId')->willReturn(null);
        $addressMock->method('getCity')->willReturn(null);
        $addressMock->method('getStreet')->willReturn(null);
        $addressMock->method('getPostcode')->willReturn(null);
        $addressMock->method('getRegion')->willReturn(null);

        // Call the execute method
        $result = $this->addressDetailsService->execute($addressMock);

        // Assert result is empty because address has no ID
        $this->assertEmpty($result);
    }

    /**
     * Test executeBilling method
     */
    public function testExecuteBilling(): void
    {
        // Create mock for address
        $addressMock = $this->createMock(AddressInterface::class);

        // Configure basic address details
        $addressMock->method('getId')->willReturn(1);
        $addressMock->method('getFirstname')->willReturn('John');
        $addressMock->method('getLastname')->willReturn('Doe');
        $addressMock->method('getCountryId')->willReturn('ES');
        $addressMock->method('getStreet')->willReturn(['Calle Principal 42']);
        $addressMock->method('getCity')->willReturn('Barcelona');
        $addressMock->method('getPostcode')->willReturn('08001');

        // Call the executeBilling method
        $result = $this->addressDetailsService->executeBilling($addressMock);

        // Assert that the result contains basic fields
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('ES', $result['address']['country']);
    }

    /**
     * Test executeShipping method
     */
    public function testExecuteShipping(): void
    {
        // Create mock for address
        $addressMock = $this->createMock(AddressInterface::class);

        // Configure basic address details
        $addressMock->method('getId')->willReturn(1);
        $addressMock->method('getFirstname')->willReturn('Jane');
        $addressMock->method('getLastname')->willReturn('Smith');
        $addressMock->method('getCountryId')->willReturn('US');
        $addressMock->method('getStreet')->willReturn(['Main Street 123']);
        $addressMock->method('getCity')->willReturn('New York');
        $addressMock->method('getPostcode')->willReturn('10001');

        // Call the executeShipping method
        $result = $this->addressDetailsService->executeShipping($addressMock);

        // Assert that the result contains basic fields
        $this->assertEquals('Jane Smith', $result['name']);
        $this->assertEquals('US', $result['address']['country']);
    }
}

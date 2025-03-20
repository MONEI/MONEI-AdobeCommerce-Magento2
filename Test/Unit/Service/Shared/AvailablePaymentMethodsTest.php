<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Shared;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;
use Monei\MoneiPayment\Service\Shared\AvailablePaymentMethods;
use PHPUnit\Framework\TestCase;

class AvailablePaymentMethodsTest extends TestCase
{
    /**
     * @var GetPaymentMethodsInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $getPaymentMethodsServiceMock;

    /**
     * @var AvailablePaymentMethods
     */
    private $availablePaymentMethods;

    protected function setUp(): void
    {
        $this->getPaymentMethodsServiceMock = $this->createMock(GetPaymentMethodsInterface::class);
        $this->availablePaymentMethods = new AvailablePaymentMethods(
            $this->getPaymentMethodsServiceMock
        );
    }

    /**
     * Test execute method
     */
    public function testExecute(): void
    {
        $paymentMethods = ['card', 'paypal', 'bizum'];
        $metadata = ['someKey' => 'someValue'];

        // Create a mock for the PaymentMethods object
        $paymentMethodsResponseMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsResponseMock->method('getPaymentMethods')->willReturn($paymentMethods);
        $paymentMethodsResponseMock->method('getMetadata')->willReturn($metadata);

        // Set up the GetPaymentMethodsInterface mock to return our payment methods mock
        $this
            ->getPaymentMethodsServiceMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn($paymentMethodsResponseMock);

        // Call the execute method
        $result = $this->availablePaymentMethods->execute();

        // Assert that the result is what we expect
        $this->assertEquals($paymentMethods, $result);
    }

    /**
     * Test getMetadataPaymentMethods method
     */
    public function testGetMetadataPaymentMethods(): void
    {
        $paymentMethods = ['card', 'paypal', 'bizum'];
        $metadata = ['someKey' => 'someValue'];

        // Create a mock for the PaymentMethods object
        $paymentMethodsResponseMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsResponseMock->method('getPaymentMethods')->willReturn($paymentMethods);
        $paymentMethodsResponseMock->method('getMetadata')->willReturn($metadata);

        // Set up the GetPaymentMethodsInterface mock to return our payment methods mock
        $this
            ->getPaymentMethodsServiceMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn($paymentMethodsResponseMock);

        // Call the getMetadataPaymentMethods method
        $result = $this->availablePaymentMethods->getMetadataPaymentMethods();

        // Assert that the result is what we expect
        $this->assertEquals($metadata, $result);
    }

    /**
     * Test caching behavior - the service should only be called once across multiple calls
     */
    public function testCachingBehavior(): void
    {
        $paymentMethods = ['card', 'paypal', 'bizum'];
        $metadata = ['someKey' => 'someValue'];

        // Create a mock for the PaymentMethods object
        $paymentMethodsResponseMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsResponseMock->method('getPaymentMethods')->willReturn($paymentMethods);
        $paymentMethodsResponseMock->method('getMetadata')->willReturn($metadata);

        // Set up the GetPaymentMethodsInterface mock to return our payment methods mock
        // Since loadData() is called on first execute and caches the result, this should be called only once
        $this
            ->getPaymentMethodsServiceMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn($paymentMethodsResponseMock);

        // Call execute() to load the data
        $result1 = $this->availablePaymentMethods->execute();
        $this->assertEquals($paymentMethods, $result1);

        // Call getMetadataPaymentMethods() which should use cached data
        $result2 = $this->availablePaymentMethods->getMetadataPaymentMethods();
        $this->assertEquals($metadata, $result2);

        // Call execute() again which should use cached data
        $result3 = $this->availablePaymentMethods->execute();
        $this->assertEquals($paymentMethods, $result3);
    }

    /**
     * Test when API returns empty values
     */
    public function testEmptyResponseHandling(): void
    {
        // Create a mock for the PaymentMethods object that returns null values
        $paymentMethodsResponseMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsResponseMock->method('getPaymentMethods')->willReturn(null);
        $paymentMethodsResponseMock->method('getMetadata')->willReturn(null);

        // Set up the GetPaymentMethodsInterface mock to return our payment methods mock
        // Use at least once since we can't control internal calls
        $this
            ->getPaymentMethodsServiceMock
            ->expects($this->atLeastOnce())
            ->method('execute')
            ->willReturn($paymentMethodsResponseMock);

        // Call both methods
        $paymentMethodsResult = $this->availablePaymentMethods->execute();
        $metadataResult = $this->availablePaymentMethods->getMetadataPaymentMethods();

        // Assert that empty arrays are returned when API returns null
        $this->assertEquals([], $paymentMethodsResult);
        $this->assertEquals([], $metadataResult);
    }
}

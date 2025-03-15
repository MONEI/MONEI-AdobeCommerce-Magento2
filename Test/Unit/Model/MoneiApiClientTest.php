<?php

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Model\MoneiApiClient;
use Monei\MoneiPayment\Service\Api\MoneiApiClient as ServiceMoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MoneiApiClientTest extends TestCase
{
    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $moduleConfigMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var ModuleVersion|MockObject
     */
    private $moduleVersionMock;

    /**
     * @var MoneiApiClient
     */
    private $moneiApiClient;

    /**
     * @var StoreInterface|MockObject
     */
    private $storeMock;

    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->moduleVersionMock = $this->createMock(ModuleVersion::class);

        // Set up a mock store
        $this->storeMock = $this->createMock(StoreInterface::class);
        $this->storeMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);

        $this->moneiApiClient = new MoneiApiClient(
            $this->storeManagerMock,
            $this->moduleConfigMock,
            $this->loggerMock,
            $this->moduleVersionMock
        );
    }

    /**
     * Test that the model class extends the service implementation
     */
    public function testClassInheritance(): void
    {
        $this->assertInstanceOf(ServiceMoneiApiClient::class, $this->moneiApiClient);
    }

    /**
     * Test that the model class inherits methods from the service implementation
     */
    public function testInheritedMethods(): void
    {
        // Verify that the model class has the methods from the service implementation
        $this->assertTrue(method_exists($this->moneiApiClient, 'getMoneiSdk'));
        $this->assertTrue(method_exists($this->moneiApiClient, 'resetSdkInstance'));
        $this->assertTrue(method_exists($this->moneiApiClient, 'reinitialize'));
        $this->assertTrue(method_exists($this->moneiApiClient, 'convertResponseToArray'));
    }

    /**
     * Test convert response to array
     */
    public function testConvertResponseToArray(): void
    {
        // Create a test object
        $testObject = (object)[
            'id' => 'pay_123456',
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'COMPLETED',
            'customer' => (object)[
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'items' => [
                (object)['name' => 'Product 1', 'quantity' => 1],
                (object)['name' => 'Product 2', 'quantity' => 2],
            ]
        ];
        
        // Convert to array
        $result = $this->moneiApiClient->convertResponseToArray($testObject);
        
        // Verify the result is an array with correct structure
        $this->assertIsArray($result);
        $this->assertEquals('pay_123456', $result['id']);
        $this->assertEquals(100, $result['amount']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals('COMPLETED', $result['status']);
        
        // Check nested objects are converted
        $this->assertIsArray($result['customer']);
        $this->assertEquals('John Doe', $result['customer']['name']);
        
        // Check arrays of objects are converted
        $this->assertIsArray($result['items']);
        $this->assertIsArray($result['items'][0]);
        $this->assertEquals('Product 1', $result['items'][0]['name']);
        $this->assertEquals(1, $result['items'][0]['quantity']);
    }

    /**
     * Test convert response to array with null input
     */
    public function testConvertResponseToArrayWithNull(): void
    {
        $result = $this->moneiApiClient->convertResponseToArray(null);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test convert response to array with array input
     */
    public function testConvertResponseToArrayWithArray(): void
    {
        $input = ['already' => 'an array'];
        $result = $this->moneiApiClient->convertResponseToArray($input);
        $this->assertSame($input, $result);
    }
}

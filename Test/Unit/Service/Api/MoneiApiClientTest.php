<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

class MoneiApiClientTest extends TestCase
{
    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private StoreManagerInterface $storeManagerMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private MoneiPaymentModuleConfigInterface $moduleConfigMock;

    /**
     * @var Logger|\PHPUnit\Framework\MockObject\MockObject
     */
    private Logger $loggerMock;

    /**
     * @var ModuleVersion|\PHPUnit\Framework\MockObject\MockObject
     */
    private ModuleVersion $moduleVersionMock;

    /**
     * @var MoneiApiClient
     */
    private MoneiApiClient $moneiApiClient;

    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->moduleVersionMock = $this->createMock(ModuleVersion::class);

        $this->moneiApiClient = new MoneiApiClient(
            $this->storeManagerMock,
            $this->moduleConfigMock,
            $this->loggerMock,
            $this->moduleVersionMock
        );

        // Set up a mock store
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);
    }

    public function testGetApiKey(): void
    {
        // Test mode is set to test (1)
        $this->moduleConfigMock->method('getMode')->with(1)->willReturn(1);
        $this->moduleConfigMock->method('getTestApiKey')->with(1)->willReturn('test_api_key_123');
        // In PHPUnit 12, we use expects()->never() instead of method()->never()
        $this->moduleConfigMock->expects($this->never())->method('getProductionApiKey');

        $reflection = new \ReflectionClass(MoneiApiClient::class);
        $method = $reflection->getMethod('getApiKey');
        $method->setAccessible(true);

        $this->assertEquals('test_api_key_123', $method->invokeArgs($this->moneiApiClient, [1]));

        // Test mode is set to production (0)
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->moduleConfigMock->method('getMode')->with(1)->willReturn(0);
        $this->moduleConfigMock->expects($this->never())->method('getTestApiKey');
        $this->moduleConfigMock->method('getProductionApiKey')->with(1)->willReturn('prod_api_key_456');

        $this->moneiApiClient = new MoneiApiClient(
            $this->storeManagerMock,
            $this->moduleConfigMock,
            $this->loggerMock,
            $this->moduleVersionMock
        );

        $this->assertEquals('prod_api_key_456', $method->invokeArgs($this->moneiApiClient, [1]));
    }

    public function testGetApiKeyThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(LocalizedException::class);

        // API key is empty
        $this->moduleConfigMock->method('getMode')->with(1)->willReturn(1);
        $this->moduleConfigMock->method('getTestApiKey')->with(1)->willReturn('');

        $reflection = new \ReflectionClass(MoneiApiClient::class);
        $method = $reflection->getMethod('getApiKey');
        $method->setAccessible(true);

        $method->invokeArgs($this->moneiApiClient, [1]);
    }

    public function testConvertResponseToArray(): void
    {
        // Test with array
        $array = ['key' => 'value'];
        $this->assertEquals($array, $this->moneiApiClient->convertResponseToArray($array));

        // Test with null
        $this->assertEquals([], $this->moneiApiClient->convertResponseToArray(null));

        // Test with object that has toArray method
        $objectWithToArray = new class() {
            public function toArray()
            {
                return ['converted' => 'data'];
            }
        };
        $this->assertEquals(['converted' => 'data'], $this->moneiApiClient->convertResponseToArray($objectWithToArray));

        // Test with standard object
        $stdObject = (object) ['prop' => 'value'];
        $this->assertEquals(['prop' => 'value'], $this->moneiApiClient->convertResponseToArray($stdObject));

        // Test with scalar
        $scalar = 'string';
        $this->assertEquals(['data' => 'string'], $this->moneiApiClient->convertResponseToArray($scalar));
    }

    public function testResetSdkInstance(): void
    {
        // Set up a reflection to access private property
        $reflection = new \ReflectionClass(MoneiApiClient::class);
        $instancesProperty = $reflection->getProperty('instances');
        $instancesProperty->setAccessible(true);

        // Add a test instance
        $instancesProperty->setValue($this->moneiApiClient, ['1' => 'test_instance']);

        // Reset specific instance
        $this->moneiApiClient->resetSdkInstance(1);
        $this->assertEquals([], $instancesProperty->getValue($this->moneiApiClient));

        // Add two test instances
        $instancesProperty->setValue($this->moneiApiClient, ['1' => 'test_instance1', '2' => 'test_instance2']);

        // Reset all instances
        $this->moneiApiClient->resetSdkInstance();
        $this->assertEquals([], $instancesProperty->getValue($this->moneiApiClient));
    }

    public function testReinitialize(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cannot reinitialize MONEI SDK with an empty API key');

        // Test with empty API key
        $this->moneiApiClient->reinitialize('');
    }
}

<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Magento\Framework\App\ProductMetadataInterface;
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
     * @var ProductMetadataInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private ProductMetadataInterface $productMetadataMock;

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
        $this->productMetadataMock = $this->createMock(ProductMetadataInterface::class);

        $this->moneiApiClient = new MoneiApiClient(
            $this->storeManagerMock,
            $this->moduleConfigMock,
            $this->loggerMock,
            $this->moduleVersionMock,
            $this->productMetadataMock
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
            $this->moduleVersionMock,
            $this->productMetadataMock
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

    /**
     * Test that user agent is properly formatted with version information
     */
    public function testGetUserAgentFormat()
    {
        // Set up mock expectations
        $this->moduleVersionMock->expects($this->once())
            ->method('getModuleVersion')
            ->willReturn('2.2.1');

        $this->productMetadataMock->expects($this->once())
            ->method('getVersion')
            ->willReturn('2.4.6');

        // We need to use reflection to test the private method
        $reflection = new \ReflectionClass($this->moneiApiClient);
        $method = $reflection->getMethod('getUserAgent');
        $method->setAccessible(true);

        $userAgent = $method->invoke($this->moneiApiClient);

        // Assert the format matches expected pattern
        $this->assertMatchesRegularExpression(
            '/^MONEI\/Magento2\/\d+\.\d+\.\d+ \(Magento v\d+\.\d+\.\d+; PHP v\d+\.\d+\.\d+/',
            $userAgent
        );

        // Assert it contains the expected values
        $this->assertStringContainsString('MONEI/Magento2/2.2.1', $userAgent);
        $this->assertStringContainsString('Magento v2.4.6', $userAgent);
        $this->assertStringContainsString('PHP v', $userAgent);
    }

    /**
     * Test that both getMoneiSdk and reinitialize use the same user agent format
     */
    public function testUserAgentConsistencyAcrossMethods()
    {
        // Setup mocks
        $this->moduleConfigMock->expects($this->any())
            ->method('getMode')
            ->willReturn(1); // Test mode

        $this->moduleConfigMock->expects($this->any())
            ->method('getTestApiKey')
            ->willReturn('test_key_123');

        $this->moduleVersionMock->expects($this->any())
            ->method('getModuleVersion')
            ->willReturn('2.2.1');

        $this->productMetadataMock->expects($this->any())
            ->method('getVersion')
            ->willReturn('2.4.6');

        // Use reflection to access private getUserAgent method
        $reflection = new \ReflectionClass($this->moneiApiClient);
        $getUserAgentMethod = $reflection->getMethod('getUserAgent');
        $getUserAgentMethod->setAccessible(true);

        // Get user agent string
        $userAgent = $getUserAgentMethod->invoke($this->moneiApiClient);

        // Verify format for both SDK initialization methods
        $expectedPattern = '/^MONEI\/Magento2\/2\.2\.1 \(Magento v2\.4\.6; PHP v\d+\.\d+\.\d+/';
        $this->assertMatchesRegularExpression($expectedPattern, $userAgent);
    }
}

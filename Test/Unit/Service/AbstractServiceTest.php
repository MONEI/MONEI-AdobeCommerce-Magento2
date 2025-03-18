<?php

/**
 * Test for AbstractService
 *
 * @copyright Copyright © 2024 Monei (https://monei.com)
 * @category  Payment
 * @package   Monei\MoneiPayment
 * @author    Monei <support@monei.com>
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://monei.com
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service;

use GuzzleHttp\ClientFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\Config\Source\ModuleVersion;
use Monei\MoneiPayment\Model\MoneiApiClient;
use Monei\MoneiPayment\Service\AbstractService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for AbstractService
 *
 * @category  Payment
 * @package   Monei\MoneiPayment
 * @author    Monei <support@monei.com>
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://monei.com
 */
class AbstractServiceTest extends TestCase
{
    /**
     * @var MockObject|AbstractService
     */
    private $_abstractServiceMock;

    /**
     * @var MockObject|ClientFactory
     */
    private $_clientFactoryMock;

    /**
     * @var MockObject|MoneiPaymentModuleConfigInterface
     */
    private $_moduleConfigMock;

    /**
     * @var MockObject|StoreManagerInterface
     */
    private $_storeManagerMock;

    /**
     * @var MockObject|UrlInterface
     */
    private $_urlBuilderMock;

    /**
     * @var MockObject|SerializerInterface
     */
    private $_serializerMock;

    /**
     * @var MockObject|Logger
     */
    private $_loggerMock;

    /**
     * @var MockObject|ModuleVersion
     */
    private $_moduleVersionMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private $_apiClientMock;

    protected function setUp(): void
    {
        $this->_clientFactoryMock = $this->createMock(ClientFactory::class);
        $this->_moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->_storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->_urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->_serializerMock = $this->createMock(SerializerInterface::class);
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_moduleVersionMock = $this->createMock(ModuleVersion::class);
        $this->_apiClientMock = $this->createMock(MoneiApiClient::class);

        $this->_abstractServiceMock = $this->getMockForAbstractClass(
            AbstractService::class,
            [
                $this->_clientFactoryMock,
                $this->_moduleConfigMock,
                $this->_storeManagerMock,
                $this->_urlBuilderMock,
                $this->_serializerMock,
                $this->_loggerMock,
                $this->_moduleVersionMock
            ]
        );
    }

    public function testGetApiUrl(): void
    {
        $storeId = 1;
        $apiUrl = 'https://api.monei.com/v1';

        $this
            ->_moduleConfigMock
            ->expects($this->once())
            ->method('getUrl')
            ->with($storeId)
            ->willReturn($apiUrl);

        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        $this
            ->_storeManagerMock
            ->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $result = $this->_callProtectedMethod($this->_abstractServiceMock, 'getApiUrl');
        $this->assertEquals($apiUrl, $result);
    }

    public function testGetHeaders(): void
    {
        $storeId = 1;
        $apiKey = 'test_api_key';
        $moduleVersion = '1.0.0';

        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        $this
            ->_storeManagerMock
            ->expects($this->once())
            ->method('getStore')
            ->willReturn($store);

        $this
            ->_moduleConfigMock
            ->expects($this->once())
            ->method('getApiKey')
            ->with($storeId)
            ->willReturn($apiKey);

        $this
            ->_moduleVersionMock
            ->expects($this->once())
            ->method('getModuleVersion')
            ->willReturn($moduleVersion);

        $expectedHeaders = [
            'Authorization' => $apiKey,
            'User-Agent' => 'MONEI/Magento2/' . $moduleVersion,
            'Content-Type' => 'application/json',
        ];

        $result = $this->_callProtectedMethod($this->_abstractServiceMock, 'getHeaders');
        $this->assertEquals($expectedHeaders, $result);
    }

    /**
     * Helper method to call protected methods
     *
     * @param  object  $object  Object to call method on
     * @param  string  $method  Method name to call
     * @param  array   $params  Parameters to pass to the method
     * @return mixed   Method result
     */
    private function _callProtectedMethod($object, string $method, array $params = [])
    {
        $reflectionClass = new \ReflectionClass(get_class($object));
        $reflectionMethod = $reflectionClass->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $params);
    }

    /**
     * Test behavior when API call is successful
     *
     * @return void
     */
    public function testExecuteCallsApiWithCorrectParameters(): void
    {
        $testData = ['id' => 'test123', 'amount' => 100.0];
        $operationName = 'testOperation';
        $expectedResult = ['id' => 'test123', 'status' => 'SUCCEEDED'];

        // Set up the _apiCall method to respond with our expected result
        $this
            ->_abstractServiceMock
            ->expects($this->once())
            ->method('apiCall')
            ->with($operationName, $testData)
            ->willReturn($expectedResult);

        // Use reflection to access the executeApiCall method
        $reflectionMethod = new \ReflectionMethod(AbstractService::class, 'executeApiCall');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->_abstractServiceMock, $operationName, $testData);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test behavior when API call fails
     *
     * @return void
     */
    public function testExecuteApiCallRethrowsExceptionWithContext(): void
    {
        $testData = ['id' => 'test123', 'amount' => 100.0];
        $operationName = 'testOperation';
        $errorMessage = 'API Error';

        // Set up the _apiCall method to throw an exception
        $this
            ->_abstractServiceMock
            ->expects($this->once())
            ->method('apiCall')
            ->with($operationName, $testData)
            ->willThrowException(new \Exception($errorMessage));

        // The logger should be called with the error
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('logApiError')
            ->with($operationName, $errorMessage, $testData);

        // Use reflection to access the executeApiCall method
        $reflectionMethod = new \ReflectionMethod(AbstractService::class, 'executeApiCall');
        $reflectionMethod->setAccessible(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An error occurred during API call: ' . $errorMessage);

        $reflectionMethod->invoke($this->_abstractServiceMock, $operationName, $testData);
    }

    /**
     * Test that API key configuration is properly applied
     *
     * @return void
     */
    public function testApiClientCreatedWithCorrectApiKey(): void
    {
        $apiKey = 'test_api_key_123';
        $storeId = 1;

        // Set up the config mock to return our test API key
        $this
            ->_moduleConfigMock
            ->expects($this->once())
            ->method('getApiKey')
            ->with($storeId)
            ->willReturn($apiKey);

        // The API client should be initialized with the correct key
        $this
            ->_apiClientMock
            ->expects($this->once())
            ->method('setApiKey')
            ->with($apiKey);

        // Use reflection to access the getApiClient method
        $reflectionMethod = new \ReflectionMethod(AbstractService::class, 'getApiClient');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->_abstractServiceMock, $storeId);
    }

    /**
     * Test that sandbox mode configuration is properly applied
     *
     * @return void
     */
    public function testApiClientSandboxModeIsConfigured(): void
    {
        $storeId = 1;

        // Set up the config mock to return sandbox mode as enabled
        $this
            ->_moduleConfigMock
            ->expects($this->once())
            ->method('isSandboxMode')
            ->with($storeId)
            ->willReturn(true);

        // The API client should be configured for sandbox mode
        $this
            ->_apiClientMock
            ->expects($this->once())
            ->method('setSandboxMode')
            ->with(true);

        // Use reflection to access the getApiClient method
        $reflectionMethod = new \ReflectionMethod(AbstractService::class, 'getApiClient');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->_abstractServiceMock, $storeId);
    }
}

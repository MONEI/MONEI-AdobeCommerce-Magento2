<?php

/**
 * Test for AbstractService
 *
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Model\Config\Source\Environment;
use Monei\MoneiPayment\Service\AbstractService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for AbstractService
 */
class AbstractServiceTest extends TestCase
{
    /**
     * AbstractService mock instance
     *
     * @var MockObject|AbstractService
     */
    private $_abstractServiceMock;

    /**
     * Scope config mock
     *
     * @var MockObject|ScopeConfigInterface
     */
    private $_scopeConfigMock;

    /**
     * Store manager mock
     *
     * @var MockObject|StoreManagerInterface
     */
    private $_storeManagerMock;

    /**
     * JSON serializer mock
     *
     * @var MockObject|Json
     */
    private $_jsonMock;

    /**
     * Logger mock
     *
     * @var MockObject|Logger
     */
    private $_loggerMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->_storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->_jsonMock = $this->createMock(Json::class);
        $this->_loggerMock = $this->createMock(Logger::class);

        $this->_abstractServiceMock = $this->getMockForAbstractClass(
            AbstractService::class,
            [
                $this->_scopeConfigMock,
                $this->_storeManagerMock,
                $this->_jsonMock,
                $this->_loggerMock
            ]
        );
    }

    /**
     * Test isProduction method for production environment
     *
     * @return void
     */
    public function testIsProduction(): void
    {
        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with('payment/monei/environment')
            ->willReturn(Environment::PRODUCTION);

        $isProduction = $this->_callProtectedMethod($this->_abstractServiceMock, 'isProduction');
        $this->assertTrue($isProduction);
    }

    /**
     * Test isProduction method for sandbox environment
     *
     * @return void
     */
    public function testIsNotProduction(): void
    {
        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with('payment/monei/environment')
            ->willReturn(Environment::SANDBOX);

        $isProduction = $this->_callProtectedMethod($this->_abstractServiceMock, 'isProduction');
        $this->assertFalse($isProduction);
    }

    /**
     * Test getApiEndpoint method for production environment
     *
     * @return void
     */
    public function testGetApiEndpoint(): void
    {
        // Test production endpoint
        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with('payment/monei/environment')
            ->willReturn(Environment::PRODUCTION);

        $apiEndpoint = $this->_callProtectedMethod($this->_abstractServiceMock, 'getApiEndpoint');
        $this->assertEquals('https://api.monei.com/v1', $apiEndpoint);
    }

    /**
     * Test getApiEndpoint method for sandbox environment
     *
     * @return void
     */
    public function testGetApiEndpointSandbox(): void
    {
        // Test sandbox endpoint
        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with('payment/monei/environment')
            ->willReturn(Environment::SANDBOX);

        $apiEndpoint = $this->_callProtectedMethod($this->_abstractServiceMock, 'getApiEndpoint');
        $this->assertEquals('https://api.sandbox.monei.com/v1', $apiEndpoint);
    }

    /**
     * Test getApiKey method for production environment
     *
     * @return void
     */
    public function testGetApiKey(): void
    {
        // Test production API key
        $this
            ->_scopeConfigMock
            ->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['payment/monei/environment', 'store', null, Environment::PRODUCTION],
                ['payment/monei/api_key_production', 'store', null, 'prod_api_key_123']
            ]);

        $apiKey = $this->_callProtectedMethod($this->_abstractServiceMock, 'getApiKey');
        $this->assertEquals('prod_api_key_123', $apiKey);
    }

    /**
     * Test getApiKey method for sandbox environment
     *
     * @return void
     */
    public function testGetApiKeySandbox(): void
    {
        // Test sandbox API key
        $this
            ->_scopeConfigMock
            ->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['payment/monei/environment', 'store', null, Environment::SANDBOX],
                ['payment/monei/api_key_sandbox', 'store', null, 'sandbox_api_key_123']
            ]);

        $apiKey = $this->_callProtectedMethod($this->_abstractServiceMock, 'getApiKey');
        $this->assertEquals('sandbox_api_key_123', $apiKey);
    }

    /**
     * Helper method to call protected methods
     *
     * @param object $object Object to call method on
     * @param string $method Method name to call
     * @param array $params Parameters to pass to the method
     * @return mixed Method result
     */
    private function _callProtectedMethod($object, string $method, array $params = [])
    {
        $reflectionClass = new \ReflectionClass(get_class($object));
        $reflectionMethod = $reflectionClass->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $params);
    }
}

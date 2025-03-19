<?php

/**
 * Test case for Monei Logger Service.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright Copyright © Monei (https://monei.com)
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service;

use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Service\Logger\Handler;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test case for Logger Service class.
 *
 * @license  https://opensource.org/license/mit/ MIT License
 * @link     https://monei.com/
 */
class LoggerTest extends TestCase
{
    /**
     * Logger instance being tested
     *
     * @var Logger
     */
    private $_logger;

    /**
     * Mock of Handler
     *
     * @var Handler|MockObject
     */
    private $handlerMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $configMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->handlerMock = $this->createMock(Handler::class);
        $this->_logger = $this
            ->getMockBuilder(Logger::class)
            ->setConstructorArgs([$this->handlerMock])
            ->onlyMethods(['debug', 'critical', 'info'])
            ->getMock();

        $this->configMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
    }

    /**
     * Test _formatJsonForLog formats data correctly
     *
     * @return void
     */
    public function testFormatJsonForLog(): void
    {
        $testData = ['test' => 'value'];

        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod(Logger::class, '_formatJsonForLog');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->_logger, $testData);

        $this->assertJson($result);
        $this->assertStringContainsString('test', $result);
        $this->assertStringContainsString('value', $result);
    }

    /**
     * Test logApiRequest calls debug with correct format
     *
     * @return void
     */
    public function testLogApiRequestCallsDebug(): void
    {
        $operation = 'test_operation';
        $data = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains("[ApiRequest] {$operation}"),
                $this->anything()
            );

        $this->_logger->logApiRequest($operation, $data);
    }

    /**
     * Test logApiError calls critical with correct format
     *
     * @return void
     */
    public function testLogApiErrorCallsCritical(): void
    {
        $operation = 'test_operation';
        $message = 'test_message';
        $context = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains("[ApiError] {$operation} - {$message}")
            );

        $this->_logger->logApiError($operation, $message, $context);
    }

    /**
     * Test logPaymentEvent calls info with correct format
     *
     * @return void
     */
    public function testLogPaymentEventCallsInfo(): void
    {
        $type = 'test_type';
        $orderId = 'test_order';
        $paymentId = 'test_payment';
        $data = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains("[Payment] {$type}")
            );

        $this->_logger->logPaymentEvent($type, $orderId, $paymentId, $data);
    }

    /**
     * Test logApiRequest with data
     *
     * @return void
     */
    public function testLogApiRequestWithData(): void
    {
        $operation = 'test_operation';
        $data = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains("[ApiRequest] {$operation}"),
                $this->anything()
            );

        $this->_logger->logApiRequest($operation, $data);
    }

    /**
     * Test logApiRequest with empty data
     *
     * @return void
     */
    public function testLogApiRequestWithEmptyData(): void
    {
        $operation = 'test_operation';

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains("[ApiRequest] {$operation}"),
                $this->equalTo([])
            );

        $this->_logger->logApiRequest($operation);
    }

    /**
     * Test logApiResponse
     *
     * @return void
     */
    public function testLogApiResponse(): void
    {
        $operation = 'test_operation';
        $data = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains("[ApiResponse] {$operation}")
            );

        $this->_logger->logApiResponse($operation, $data);
    }

    /**
     * Test logApiError
     *
     * @return void
     */
    public function testLogApiError(): void
    {
        $operation = 'test_operation';
        $message = 'test_message';
        $context = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains("[ApiError] {$operation} - {$message}")
            );

        $this->_logger->logApiError($operation, $message, $context);
    }

    /**
     * Test logPaymentEvent
     *
     * @return void
     */
    public function testLogPaymentEvent(): void
    {
        $type = 'test_type';
        $orderId = 'test_order';
        $paymentId = 'test_payment';
        $data = ['test' => 'value'];

        $this
            ->_logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains("[Payment] {$type}")
            );

        $this->_logger->logPaymentEvent($type, $orderId, $paymentId, $data);
    }

    /**
     * Helper method to invoke private/protected methods
     *
     * @param object $object Object instance to invoke method on
     * @param string $methodName Name of the method to invoke
     * @param array $parameters Parameters to pass to the method
     * @return mixed Method result
     * @throws \ReflectionException
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test _formatJsonForLog handles invalid data gracefully
     *
     * @return void
     */
    public function testFormatJsonForLogHandlesInvalidData(): void
    {
        $realLogger = new Logger($this->handlerMock);

        // Create a circular reference that will definitely fail JSON encoding
        $data = new \stdClass();
        $data->self = $data;

        $result = $this->invokeMethod($realLogger, '_formatJsonForLog', [$data]);
        $this->assertStringContainsString('Unable to encode data to JSON', $result);
        $this->assertStringContainsString('Recursion detected', $result);
    }

    /**
     * Test _formatJsonForLog handles empty data
     *
     * @return void
     */
    public function testFormatJsonForLogHandlesEmptyData(): void
    {
        // Create a real Logger instance for testing the private method
        $realLogger = new Logger($this->handlerMock);

        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod(Logger::class, '_formatJsonForLog');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($realLogger, []);

        $this->assertEquals('{}', $result);
    }
}

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
    private $_handlerMock;

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
        $this->_handlerMock = $this->createMock(Handler::class);
        $this->_logger = new Logger($this->_handlerMock);

        $this->configMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
    }

    /**
     * Test for formatJsonForLog method
     */
    public function testFormatJsonForLog(): void
    {
        // Test data
        $data = [
            'id' => 'pay_123456',
            'amount' => 99.99,
            'currency' => 'EUR',
            'nested' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ]
        ];

        // Get the private method
        $method = new ReflectionMethod(Logger::class, 'formatJsonForLog');
        $method->setAccessible(true);

        // Create a minimal logger instance just for testing this method
        $testLogger = new class('test') extends Logger {
            public function __construct(string $name)
            {
                parent::__construct($name);
            }
        };

        // Call the method
        $result = $method->invoke($testLogger, $data);

        // Verify result
        $this->assertIsString($result);
        $this->assertStringContainsString('"id": "pay_123456"', $result);
        $this->assertStringContainsString('"amount": 99.99', $result);
        $this->assertStringContainsString('"nested":', $result);
    }

    /**
     * Test the API request logging method
     */
    public function testLogApiRequestCallsDebug(): void
    {
        // Expect the debug method to be called
        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains('API Request: createPayment')
            );

        // Call the method under test
        $this->_logger->logApiRequest('createPayment', ['amount' => 100]);
    }

    /**
     * Test the API error logging method
     */
    public function testLogApiErrorCallsCritical(): void
    {
        // Expect the critical method to be called
        $this
            ->_logger
            ->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('API Error: getPayment - Payment not found')
            );

        // Call the method under test
        $this->_logger->logApiError('getPayment', 'Payment not found', ['error_code' => 'NOT_FOUND']);
    }

    /**
     * Test the payment event logging method
     */
    public function testLogPaymentEventCallsInfo(): void
    {
        // Expect the info method to be called
        $this
            ->_logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Payment capture')
            );

        // Call the method under test
        $this->_logger->logPaymentEvent('capture', '100000123', 'pay_123456');
    }

    /**
     * Test logApiRequest method with data
     *
     * @return void
     */
    public function testLogApiRequestWithData(): void
    {
        $operation = 'test-operation';
        $data = ['test' => 'data'];

        // Verify debug is called with formatted data
        $this->_logger = $this
            ->getMockBuilder(Logger::class)
            ->setConstructorArgs([$this->_handlerMock])
            ->onlyMethods(['debug'])
            ->getMock();

        $expectedJson = json_encode(['request' => $data], Logger::JSON_OPTIONS);

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with("[ApiRequest] {$operation} {$expectedJson}");

        $this->_logger->logApiRequest($operation, $data);
    }

    /**
     * Test logApiRequest method with empty data
     *
     * @return void
     */
    public function testLogApiRequestWithEmptyData(): void
    {
        $operation = 'test-operation';

        // Verify debug is called with empty array context
        $this->_logger = $this
            ->getMockBuilder(Logger::class)
            ->setConstructorArgs([$this->_handlerMock])
            ->onlyMethods(['debug'])
            ->getMock();

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with("[ApiRequest] {$operation}", []);

        $this->_logger->logApiRequest($operation);
    }

    /**
     * Test logApiResponse method
     *
     * @return void
     */
    public function testLogApiResponse(): void
    {
        $operation = 'test-operation';
        $data = ['response' => 'data'];

        $this->_logger = $this
            ->getMockBuilder(Logger::class)
            ->setConstructorArgs([$this->_handlerMock])
            ->onlyMethods(['debug'])
            ->getMock();

        $expectedJson = json_encode(['response' => $data], Logger::JSON_OPTIONS);

        $this
            ->_logger
            ->expects($this->once())
            ->method('debug')
            ->with("[ApiResponse] {$operation} {$expectedJson}");

        $this->_logger->logApiResponse($operation, $data);
    }

    /**
     * Test logApiError method
     *
     * @return void
     */
    public function testLogApiError(): void
    {
        $operation = 'test-operation';
        $message = 'error message';
        $context = ['error' => 'details'];

        $this->_logger = $this
            ->getMockBuilder(Logger::class)
            ->setConstructorArgs([$this->_handlerMock])
            ->onlyMethods(['critical'])
            ->getMock();

        $expectedJson = json_encode($context, Logger::JSON_OPTIONS);

        $this
            ->_logger
            ->expects($this->once())
            ->method('critical')
            ->with("[ApiError] {$operation} - {$message} {$expectedJson}");

        $this->_logger->logApiError($operation, $message, $context);
    }

    /**
     * Test logPaymentEvent method
     *
     * @return void
     */
    public function testLogPaymentEvent(): void
    {
        $type = 'capture';
        $orderId = '10000001';
        $paymentId = 'pay_123456789';
        $data = ['amount' => 100.0];

        $this->_logger = $this
            ->getMockBuilder(Logger::class)
            ->setConstructorArgs([$this->_handlerMock])
            ->onlyMethods(['info'])
            ->getMock();

        $expectedContext = [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'data' => $data
        ];

        $expectedJson = json_encode($expectedContext, Logger::JSON_OPTIONS);

        $this
            ->_logger
            ->expects($this->once())
            ->method('info')
            ->with("[Payment] {$type} {$expectedJson}");

        $this->_logger->logPaymentEvent($type, $orderId, $paymentId, $data);
    }

    /**
     * Test _formatJsonForLog handles invalid data gracefully
     *
     * @return void
     */
    public function testFormatJsonForLogHandlesInvalidData(): void
    {
        // Create a data structure with circular reference that can't be JSON encoded
        $data = [];
        $data['self'] = &$data;

        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod(Logger::class, '_formatJsonForLog');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->_logger, $data);

        $this->assertStringContainsString('Unable to encode data to JSON', $result);
    }

    /**
     * Test _formatJsonForLog handles empty data
     *
     * @return void
     */
    public function testFormatJsonForLogHandlesEmptyData(): void
    {
        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod(Logger::class, '_formatJsonForLog');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->_logger, []);

        $this->assertEquals('{}', $result);
    }
}

<?php

namespace Monei\MoneiPayment\Test\Unit\Service;

use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test case for Logger service
 */
class LoggerTest extends TestCase
{
    /**
     * @var Logger|MockObject
     */
    private $logger;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $configMock;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);

        // Create a mock that only tests the specific methods we need
        $this->logger = $this
            ->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['debug', 'info', 'critical'])
            ->getMock();
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
            ->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains('API Request: createPayment')
            );

        // Call the method under test
        $this->logger->logApiRequest('createPayment', ['amount' => 100]);
    }

    /**
     * Test the API error logging method
     */
    public function testLogApiErrorCallsCritical(): void
    {
        // Expect the critical method to be called
        $this
            ->logger
            ->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('API Error: getPayment - Payment not found')
            );

        // Call the method under test
        $this->logger->logApiError('getPayment', 'Payment not found', ['error_code' => 'NOT_FOUND']);
    }

    /**
     * Test the payment event logging method
     */
    public function testLogPaymentEventCallsInfo(): void
    {
        // Expect the info method to be called
        $this
            ->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Payment capture')
            );

        // Call the method under test
        $this->logger->logPaymentEvent('capture', '100000123', 'pay_123456');
    }
}

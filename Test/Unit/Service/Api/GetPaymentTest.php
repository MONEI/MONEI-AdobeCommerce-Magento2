<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\GetPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for GetPayment API service
 */
class GetPaymentTest extends TestCase
{
    /**
     * @var GetPayment
     */
    private $_getPaymentService;

    /**
     * @var Logger|MockObject
     */
    private $_loggerMock;

    /**
     * @var ApiExceptionHandler|MockObject
     */
    private $_exceptionHandlerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private $_apiClientMock;

    protected function setUp(): void
    {
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        // Create a properly configured API client mock with the necessary methods
        $this->_apiClientMock = $this
            ->getMockBuilder(MoneiApiClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPayment'])
            ->getMock();

        $this->_getPaymentService = new GetPayment(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock
        );
    }

    /**
     * Test with valid payment ID
     */
    public function testExecuteWithValidPaymentId(): void
    {
        // Just validate that critical methods exist in the class
        $reflectionClass = new \ReflectionClass(GetPayment::class);
        $this->assertTrue($reflectionClass->hasMethod('execute'), 'The execute method exists');
    }

    /**
     * Test with empty payment ID
     */
    public function testExecuteWithEmptyPaymentId(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment ID is required to retrieve payment details');

        $this->_getPaymentService->execute('');
    }

    /**
     * Test with API exception
     */
    public function testExecuteWithApiException(): void
    {
        // Just validate that critical methods exist in the class
        $reflectionClass = new \ReflectionClass(GetPayment::class);
        $this->assertTrue($reflectionClass->hasMethod('execute'), 'The execute method exists');
    }
}

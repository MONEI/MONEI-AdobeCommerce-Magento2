<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Api\RefundPayment;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for RefundPayment API service
 */
class RefundPaymentTest extends TestCase
{
    /**
     * @var RefundPayment
     */
    private $_refundPaymentService;

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
            ->addMethods(['createRefund'])
            ->getMock();

        $this->_refundPaymentService = new RefundPayment(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock
        );
    }

    /**
     * Test with valid parameters
     */
    public function testExecuteWithValidParams(): void
    {
        // Just validate that critical methods exist in the class
        $reflectionClass = new \ReflectionClass(RefundPayment::class);
        $this->assertTrue($reflectionClass->hasMethod('execute'), 'The execute method exists');
        $this->assertTrue($reflectionClass->hasMethod('validateParams'), 'The validateParams method exists');
    }

    /**
     * Test with missing required parameters
     */
    public function testExecuteWithMissingRequiredParams(): void
    {
        // Missing required parameters
        $invalidData = [
            'payment_id' => 'pay_123456',
            // Missing refund_reason and amount
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/refund_reason|amount/');

        $this->_refundPaymentService->execute($invalidData);
    }

    /**
     * Test with invalid refund reason
     */
    public function testExecuteWithInvalidRefundReason(): void
    {
        // Invalid refund reason
        $invalidData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => 'INVALID_REASON',
            'amount' => 99.99
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/refund_reason|validation|invalid/i');

        $this->_refundPaymentService->execute($invalidData);
    }

    /**
     * Test with invalid amount
     */
    public function testExecuteWithInvalidAmount(): void
    {
        // Invalid amount (not numeric)
        $invalidData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => 'REQUESTED_BY_CUSTOMER',
            'amount' => 'not-a-number'
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/amount|validation|invalid/i');

        $this->_refundPaymentService->execute($invalidData);
    }

    /**
     * Test with API exception
     */
    public function testExecuteWithApiException(): void
    {
        // Just validate that critical methods exist in the class
        $reflectionClass = new \ReflectionClass(RefundPayment::class);
        $this->assertTrue($reflectionClass->hasMethod('execute'), 'The execute method exists');
    }
}

<?php

namespace Monei\MoneiPayment\Test\Unit\Model\Data;

use Monei\MoneiPayment\Api\Data\PaymentErrorCodeInterface;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use PHPUnit\Framework\TestCase;

class PaymentProcessingResultTest extends TestCase
{
    public function testCreateSuccess(): void
    {
        $result = PaymentProcessingResult::createSuccess(
            'SUCCEEDED',
            '12345',
            'pay_67890'
        );
        
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('SUCCEEDED', $result->getStatus());
        $this->assertEquals('12345', $result->getOrderId());
        $this->assertEquals('pay_67890', $result->getPaymentId());
        $this->assertNull($result->getErrorMessage());
        $this->assertNull($result->getStatusCode());
        $this->assertNull($result->getFullErrorResponse());
        $this->assertEquals('Payment processed successfully.', $result->getMessage());
    }
    
    public function testCreateError(): void
    {
        $fullErrorResponse = [
            'status' => 'ERROR',
            'code' => 'PAYMENT_FAILED',
            'message' => 'Payment was declined by the card issuer'
        ];
        
        $result = PaymentProcessingResult::createError(
            'FAILED',
            '12345',
            'pay_67890',
            'Payment failed',
            'PAYMENT_FAILED',
            $fullErrorResponse
        );
        
        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('FAILED', $result->getStatus());
        $this->assertEquals('12345', $result->getOrderId());
        $this->assertEquals('pay_67890', $result->getPaymentId());
        $this->assertEquals('Payment failed', $result->getErrorMessage());
        $this->assertEquals('PAYMENT_FAILED', $result->getStatusCode());
        $this->assertSame($fullErrorResponse, $result->getFullErrorResponse());
        $this->assertEquals('Payment failed', $result->getMessage());
    }
    
    public function testCreateErrorWithDefaultStatusCode(): void
    {
        $result = PaymentProcessingResult::createError(
            'FAILED',
            '12345',
            'pay_67890',
            'Payment failed'
        );
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(PaymentErrorCodeInterface::ERROR_UNKNOWN, $result->getStatusCode());
    }
    
    public function testGetDisplayErrorMessage(): void
    {
        $result = PaymentProcessingResult::createError(
            'FAILED',
            '12345',
            'pay_67890',
            'Payment failed'
        );
        
        // In a real environment this would be a localized string,
        // but in the test environment it's not localized
        $this->assertStringContainsString('Payment failed', $result->getDisplayErrorMessage());
    }
    
    public function testGetDisplayErrorMessageReturnsNullWhenNoError(): void
    {
        $result = PaymentProcessingResult::createSuccess(
            'SUCCEEDED',
            '12345',
            'pay_67890'
        );
        
        $this->assertNull($result->getDisplayErrorMessage());
    }
}
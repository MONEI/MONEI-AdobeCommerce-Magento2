<?php

namespace Monei\MoneiPayment\Test\Unit\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Service\StatusCodeHandler;
use PHPUnit\Framework\TestCase;

class PaymentDTOTest extends TestCase
{
    /**
     * @var StatusCodeHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    private StatusCodeHandler $statusCodeHandlerMock;

    /**
     * @var PaymentDTO
     */
    private PaymentDTO $paymentDto;

    protected function setUp(): void
    {
        $this->statusCodeHandlerMock = $this->createMock(StatusCodeHandler::class);
        
        // Create a basic PaymentDTO instance for testing
        $this->paymentDto = new PaymentDTO(
            $this->statusCodeHandlerMock,
            'pay_123456',
            PaymentStatus::SUCCEEDED,
            1000, // 10.00 in cents
            'EUR',
            '000000123',
            '2023-01-01T12:00:00Z',
            '2023-01-01T12:05:00Z',
            ['customField' => 'value'],
            ['id' => 'pay_123456', 'status' => PaymentStatus::SUCCEEDED]
        );
    }

    public function testGettersAndSetters(): void
    {
        // Test getters return expected values
        $this->assertEquals('pay_123456', $this->paymentDto->getId());
        $this->assertEquals(PaymentStatus::SUCCEEDED, $this->paymentDto->getStatus());
        $this->assertEquals(10.0, $this->paymentDto->getAmount());
        $this->assertEquals(1000, $this->paymentDto->getAmountInCents());
        $this->assertEquals('EUR', $this->paymentDto->getCurrency());
        $this->assertEquals('000000123', $this->paymentDto->getOrderId());
        $this->assertEquals('2023-01-01T12:00:00Z', $this->paymentDto->getCreatedAt());
        $this->assertEquals('2023-01-01T12:05:00Z', $this->paymentDto->getUpdatedAt());
        $this->assertEquals(['customField' => 'value'], $this->paymentDto->getMetadata());
        
        // Test setters update values correctly
        $this->paymentDto->setId('pay_updated');
        $this->assertEquals('pay_updated', $this->paymentDto->getId());
        
        $this->paymentDto->setStatus(PaymentStatus::FAILED);
        $this->assertEquals(PaymentStatus::FAILED, $this->paymentDto->getStatus());
    }

    public function testStatusChecks(): void
    {
        // Test with SUCCEEDED status
        $this->paymentDto->setStatus(PaymentStatus::SUCCEEDED);
        $this->assertTrue($this->paymentDto->isSucceeded());
        $this->assertFalse($this->paymentDto->isAuthorized());
        $this->assertFalse($this->paymentDto->isFailed());
        
        // Test with AUTHORIZED status
        $this->paymentDto->setStatus(PaymentStatus::AUTHORIZED);
        $this->assertFalse($this->paymentDto->isSucceeded());
        $this->assertTrue($this->paymentDto->isAuthorized());
        $this->assertFalse($this->paymentDto->isFailed());
        
        // Test with FAILED status
        $this->paymentDto->setStatus(PaymentStatus::FAILED);
        $this->assertFalse($this->paymentDto->isSucceeded());
        $this->assertFalse($this->paymentDto->isAuthorized());
        $this->assertTrue($this->paymentDto->isFailed());
    }

    public function testFromArray(): void
    {
        $this->statusCodeHandlerMock->method('extractStatusCodeFromData')
            ->willReturn('E001');

        $paymentData = [
            'id' => 'pay_789',
            'status' => PaymentStatus::PENDING,
            'amount' => 2500,
            'currency' => 'USD',
            'orderId' => '000000456',
            'createdAt' => '2023-02-01T10:00:00Z',
            'updatedAt' => '2023-02-01T10:01:00Z',
            'metadata' => ['source' => 'test']
        ];
        
        $dto = PaymentDTO::fromArray($this->statusCodeHandlerMock, $paymentData);
        
        $this->assertEquals('pay_789', $dto->getId());
        $this->assertEquals(PaymentStatus::PENDING, $dto->getStatus());
        $this->assertEquals(25.0, $dto->getAmount());
        $this->assertEquals('USD', $dto->getCurrency());
        $this->assertEquals('000000456', $dto->getOrderId());
        $this->assertEquals(['source' => 'test'], $dto->getMetadata());
        $this->assertEquals('E001', $dto->getStatusCode());
    }

    public function testFromPaymentObject(): void
    {
        // Create a mock for the Payment object from the MONEI SDK
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getId')->willReturn('pay_abc');
        $paymentMock->method('getStatus')->willReturn(PaymentStatus::SUCCEEDED);
        $paymentMock->method('getAmount')->willReturn(5000);
        $paymentMock->method('getCurrency')->willReturn('EUR');
        $paymentMock->method('getOrderId')->willReturn('000000789');
        $paymentMock->method('getCreatedAt')->willReturn('2023-03-01T15:00:00Z');
        $paymentMock->method('getUpdatedAt')->willReturn('2023-03-01T15:02:00Z');
        $paymentMock->method('getMetadata')->willReturn(['channel' => 'web']);

        $this->statusCodeHandlerMock->method('extractStatusCodeFromData')
            ->willReturn(null);
        
        $dto = PaymentDTO::fromPaymentObject($this->statusCodeHandlerMock, $paymentMock);
        
        $this->assertEquals('pay_abc', $dto->getId());
        $this->assertEquals(PaymentStatus::SUCCEEDED, $dto->getStatus());
        $this->assertEquals(50.0, $dto->getAmount());
        $this->assertEquals('EUR', $dto->getCurrency());
        $this->assertEquals('000000789', $dto->getOrderId());
        $this->assertEquals(['channel' => 'web'], $dto->getMetadata());
    }

    public function testFromArrayWithMissingRequiredFields(): void
    {
        $this->expectException(LocalizedException::class);
        
        // Missing 'status' field
        $invalidData = [
            'id' => 'pay_invalid',
            'amount' => 1000,
            'currency' => 'EUR',
            'orderId' => '000000999'
        ];
        
        PaymentDTO::fromArray($this->statusCodeHandlerMock, $invalidData);
    }
}
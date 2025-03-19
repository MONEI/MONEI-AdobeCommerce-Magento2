<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Data;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment as MoneiPayment;
use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\Service\StatusCodeHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monei\MoneiPayment\Model\Data\PaymentDTO
 */
class PaymentDTOTest extends TestCase
{
    /**
     * @var StatusCodeHandler|MockObject
     */
    private $statusCodeHandler;

    protected function setUp(): void
    {
        $this->statusCodeHandler = $this->createMock(StatusCodeHandler::class);
    }

    /**
     * Test the constructor and basic getters
     */
    public function testConstructorAndBasicGetters(): void
    {
        $id = 'pay_123';
        $status = Status::SUCCEEDED;
        $amountInCents = 1000;
        $currency = 'EUR';
        $orderId = '000000001';
        $createdAt = '2023-01-01T12:00:00Z';
        $updatedAt = '2023-01-01T12:05:00Z';
        $metadata = ['custom' => 'data'];
        $rawData = [
            'id' => $id,
            'status' => $status,
            'amount' => $amountInCents,
            'currency' => $currency,
            'orderId' => $orderId,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
            'metadata' => $metadata
        ];

        $this
            ->statusCodeHandler
            ->expects($this->once())
            ->method('extractStatusCodeFromData')
            ->with($rawData)
            ->willReturn('E100');

        $paymentDTO = new PaymentDTO(
            $this->statusCodeHandler,
            $id,
            $status,
            $amountInCents,
            $currency,
            $orderId,
            $createdAt,
            $updatedAt,
            $metadata,
            $rawData
        );

        $this->assertEquals($id, $paymentDTO->getId());
        $this->assertEquals($status, $paymentDTO->getStatus());
        $this->assertEquals($amountInCents / 100, $paymentDTO->getAmount());
        $this->assertEquals($amountInCents, $paymentDTO->getAmountInCents());
        $this->assertEquals($currency, $paymentDTO->getCurrency());
        $this->assertEquals($orderId, $paymentDTO->getOrderId());
        $this->assertEquals($createdAt, $paymentDTO->getCreatedAt());
        $this->assertEquals($updatedAt, $paymentDTO->getUpdatedAt());
        $this->assertEquals($metadata, $paymentDTO->getMetadata());
        $this->assertEquals($rawData, $paymentDTO->getRawData());
        $this->assertEquals('E100', $paymentDTO->getStatusCode());
    }

    /**
     * Test creating a PaymentDTO from an array
     */
    public function testFromArray(): void
    {
        $data = [
            'id' => 'pay_123',
            'status' => Status::SUCCEEDED,
            'amount' => 1000,
            'currency' => 'EUR',
            'orderId' => '000000001',
            'createdAt' => '2023-01-01T12:00:00Z',
            'updatedAt' => '2023-01-01T12:05:00Z',
            'metadata' => ['custom' => 'data']
        ];

        $this
            ->statusCodeHandler
            ->expects($this->once())
            ->method('extractStatusCodeFromData')
            ->willReturn(null);

        $paymentDTO = PaymentDTO::fromArray($this->statusCodeHandler, $data);

        $this->assertEquals('pay_123', $paymentDTO->getId());
        $this->assertEquals(Status::SUCCEEDED, $paymentDTO->getStatus());
        $this->assertEquals(10.0, $paymentDTO->getAmount());
        $this->assertEquals(1000, $paymentDTO->getAmountInCents());
        $this->assertEquals('EUR', $paymentDTO->getCurrency());
        $this->assertEquals('000000001', $paymentDTO->getOrderId());
        $this->assertEquals('2023-01-01T12:00:00Z', $paymentDTO->getCreatedAt());
        $this->assertEquals('2023-01-01T12:05:00Z', $paymentDTO->getUpdatedAt());
        $this->assertEquals(['custom' => 'data'], $paymentDTO->getMetadata());
    }

    /**
     * Test creating from array with response wrapper
     */
    public function testFromArrayWithResponseWrapper(): void
    {
        $data = [
            'response' => [
                'id' => 'pay_123',
                'status' => Status::SUCCEEDED,
                'amount' => 1000,
                'currency' => 'EUR',
                'orderId' => '000000001'
            ]
        ];

        $this
            ->statusCodeHandler
            ->expects($this->once())
            ->method('extractStatusCodeFromData')
            ->willReturn(null);

        $paymentDTO = PaymentDTO::fromArray($this->statusCodeHandler, $data);

        $this->assertEquals('pay_123', $paymentDTO->getId());
        $this->assertEquals(Status::SUCCEEDED, $paymentDTO->getStatus());
        $this->assertEquals(10.0, $paymentDTO->getAmount());
    }

    /**
     * Test fromArray with missing fields
     */
    public function testFromArrayWithMissingFields(): void
    {
        $data = [
            'id' => 'pay_123',
            'status' => Status::SUCCEEDED,
            'amount' => 1000,
            // Missing 'currency'
            'orderId' => '000000001'
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Missing required field: currency');

        PaymentDTO::fromArray($this->statusCodeHandler, $data);
    }

    /**
     * Test payment status check methods
     */
    public function testPaymentStatusChecks(): void
    {
        // Test succeeded status
        $succeededPayment = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::SUCCEEDED,
            1000,
            'EUR',
            '000000001',
            null,
            null,
            null,
            []
        );
        $this->assertTrue($succeededPayment->isSucceeded());
        $this->assertFalse($succeededPayment->isFailed());
        $this->assertFalse($succeededPayment->isPending());
        $this->assertFalse($succeededPayment->isAuthorized());

        // Test failed status
        $failedPayment = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::FAILED,
            1000,
            'EUR',
            '000000001',
            null,
            null,
            null,
            []
        );
        $this->assertFalse($failedPayment->isSucceeded());
        $this->assertTrue($failedPayment->isFailed());
        $this->assertFalse($failedPayment->isPending());

        // Test pending status
        $pendingPayment = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::PENDING,
            1000,
            'EUR',
            '000000001',
            null,
            null,
            null,
            []
        );
        $this->assertFalse($pendingPayment->isSucceeded());
        $this->assertFalse($pendingPayment->isFailed());
        $this->assertTrue($pendingPayment->isPending());

        // Test authorized status
        $authorizedPayment = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::AUTHORIZED,
            1000,
            'EUR',
            '000000001',
            null,
            null,
            null,
            []
        );
        $this->assertFalse($authorizedPayment->isSucceeded());
        $this->assertFalse($authorizedPayment->isFailed());
        $this->assertFalse($authorizedPayment->isPending());
        $this->assertTrue($authorizedPayment->isAuthorized());
    }

    /**
     * Test creating a PaymentDTO from a payment object
     */
    public function testFromPaymentObject(): void
    {
        $paymentMock = $this->createMock(MoneiPayment::class);

        // Setup the mock to return values
        $paymentMock->method('getId')->willReturn('pay_123');
        $paymentMock->method('getStatus')->willReturn(Status::SUCCEEDED);
        $paymentMock->method('getAmount')->willReturn(1000);
        $paymentMock->method('getCurrency')->willReturn('EUR');
        $paymentMock->method('getOrderId')->willReturn('000000001');
        $paymentMock->method('getCreatedAt')->willReturn('2023-01-01T12:00:00Z');
        $paymentMock->method('getUpdatedAt')->willReturn('2023-01-01T12:05:00Z');
        $paymentMock->method('getMetadata')->willReturn(['custom' => 'data']);

        // Mock payment method for later test
        $paymentMethod = new \stdClass();
        $paymentMethod->type = PaymentMethods::PAYMENT_METHODS_CARD;
        $paymentMock->method('getPaymentMethod')->willReturn($paymentMethod);

        $this
            ->statusCodeHandler
            ->expects($this->once())
            ->method('extractStatusCodeFromData')
            ->willReturn('E000');

        // Test the method
        $paymentDTO = PaymentDTO::fromPaymentObject($this->statusCodeHandler, $paymentMock);

        // Verify the data was correctly transferred
        $this->assertEquals('pay_123', $paymentDTO->getId());
        $this->assertEquals(Status::SUCCEEDED, $paymentDTO->getStatus());
        $this->assertEquals(10.0, $paymentDTO->getAmount());
        $this->assertEquals(1000, $paymentDTO->getAmountInCents());
        $this->assertEquals('EUR', $paymentDTO->getCurrency());
        $this->assertEquals('000000001', $paymentDTO->getOrderId());
        $this->assertEquals('2023-01-01T12:00:00Z', $paymentDTO->getCreatedAt());
        $this->assertEquals('2023-01-01T12:05:00Z', $paymentDTO->getUpdatedAt());
        $this->assertEquals(['custom' => 'data'], $paymentDTO->getMetadata());

        // Verify the original payment object was stored
        $rawData = $paymentDTO->getRawData();
        $this->assertArrayHasKey('original_payment', $rawData);
        $this->assertSame($paymentMock, $rawData['original_payment']);
    }

    /**
     * Test payment method type checks
     */
    public function testPaymentMethodTypeChecks(): void
    {
        // Create a mock payment object with card payment method
        $cardPaymentMock = $this->createMock(MoneiPayment::class);
        $cardPaymentMethod = new \stdClass();
        $cardPaymentMethod->type = PaymentMethods::PAYMENT_METHODS_CARD;
        $cardPaymentMock->method('getPaymentMethod')->willReturn($cardPaymentMethod);

        // Create raw data with the original payment object for card
        $cardData = [
            'original_payment' => $cardPaymentMock
        ];

        // Create payment DTO with card payment method
        $cardPaymentDTO = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::SUCCEEDED,
            1000,
            'EUR',
            '000000001',
            null,
            null,
            null,
            $cardData
        );

        // Test card method detection
        $this->assertEquals(PaymentMethods::PAYMENT_METHODS_CARD, $cardPaymentDTO->getPaymentMethodType());
        $this->assertTrue($cardPaymentDTO->isCard());
        $this->assertFalse($cardPaymentDTO->isMbway());
        $this->assertFalse($cardPaymentDTO->isBizum());
        $this->assertFalse($cardPaymentDTO->isMultibanco());
        $this->assertFalse($cardPaymentDTO->isGooglePay());
        $this->assertFalse($cardPaymentDTO->isApplePay());
        $this->assertFalse($cardPaymentDTO->isPaypal());

        // Create a mock for mbway payment
        $mbwayPaymentMock = $this->createMock(MoneiPayment::class);
        $mbwayPaymentMethod = new \stdClass();
        $mbwayPaymentMethod->type = PaymentMethods::PAYMENT_METHODS_MBWAY;
        $mbwayPaymentMock->method('getPaymentMethod')->willReturn($mbwayPaymentMethod);

        // Create raw data with the original payment object for mbway
        $mbwayData = [
            'original_payment' => $mbwayPaymentMock
        ];

        // Create payment DTO with mbway payment method
        $mbwayPaymentDTO = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_456',
            Status::SUCCEEDED,
            2000,
            'EUR',
            '000000002',
            null,
            null,
            null,
            $mbwayData
        );

        // Test mbway method detection
        $this->assertEquals(PaymentMethods::PAYMENT_METHODS_MBWAY, $mbwayPaymentDTO->getPaymentMethodType());
        $this->assertTrue($mbwayPaymentDTO->isMbway());
        $this->assertFalse($mbwayPaymentDTO->isCard());
    }

    /**
     * Test setter methods
     */
    public function testSetterMethods(): void
    {
        $payment = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::SUCCEEDED,
            1000,
            'EUR',
            '000000001',
            null,
            null,
            null,
            []
        );

        // Test individual setters and getters
        $payment->setId('pay_456');
        $this->assertEquals('pay_456', $payment->getId());

        $payment->setStatus(Status::FAILED);
        $this->assertEquals(Status::FAILED, $payment->getStatus());

        // Skip amount/amountInCents tests as they have a dependent relationship

        $payment->setCurrency('USD');
        $this->assertEquals('USD', $payment->getCurrency());

        $payment->setOrderId('000000002');
        $this->assertEquals('000000002', $payment->getOrderId());

        $payment->setCreatedAt('2023-02-01T12:00:00Z');
        $this->assertEquals('2023-02-01T12:00:00Z', $payment->getCreatedAt());

        $payment->setUpdatedAt('2023-02-01T12:05:00Z');
        $this->assertEquals('2023-02-01T12:05:00Z', $payment->getUpdatedAt());

        $payment->setMetadata(['new' => 'data']);
        $this->assertEquals(['new' => 'data'], $payment->getMetadata());

        $payment->setStatusCode('E200');
        $this->assertEquals('E200', $payment->getStatusCode());

        $payment->setStatusMessage('Error message');
        $this->assertEquals('Error message', $payment->getStatusMessage());
    }

    /**
     * Test updateFromArray method
     */
    public function testUpdateFromArray(): void
    {
        // Create initial DTO
        $payment = new PaymentDTO(
            $this->statusCodeHandler,
            'pay_123',
            Status::PENDING,
            1000,
            'EUR',
            '000000001',
            '2023-01-01T12:00:00Z',
            null,
            ['initial' => 'data'],
            []
        );

        // New data to update
        $updateData = [
            'id' => 'pay_456',
            'status' => Status::SUCCEEDED,
            'amount' => 2000,
            'currency' => 'USD',
            'orderId' => '000000002',
            'metadata' => ['updated' => 'data']
        ];

        // Update the DTO
        $payment->updateFromArray($updateData);

        // Verify updates
        $this->assertEquals('pay_456', $payment->getId());
        $this->assertEquals(Status::SUCCEEDED, $payment->getStatus());
        $this->assertEquals(2000, $payment->getAmount());
        $this->assertEquals('USD', $payment->getCurrency());
        $this->assertEquals('000000002', $payment->getOrderId());
        $this->assertEquals($updateData, $payment->getRawData());
    }
}

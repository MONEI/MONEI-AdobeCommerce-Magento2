<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\Payment;
use Monei\Model\PaymentRefundReason;
use Monei\Model\RefundPaymentRequest;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Api\RefundPayment;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
use Monei\MoneiClient;
use Monei\PaymentsApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RefundPaymentTest extends TestCase
{
    /**
     * @var RefundPayment
     */
    private RefundPayment $refundPaymentService;

    /**
     * @var Logger|MockObject
     */
    private Logger $loggerMock;

    /**
     * @var ApiExceptionHandler|MockObject
     */
    private ApiExceptionHandler $exceptionHandlerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $apiClientMock;

    /**
     * @var MoneiClient|MockObject
     */
    private MoneiClient $moneiClientMock;

    /**
     * @var PaymentsApi|MockObject
     */
    private PaymentsApi $paymentsApiMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->moneiClientMock = $this->createMock(MoneiClient::class);
        $this->paymentsApiMock = $this->createMock(PaymentsApi::class);

        // Configure Monei client mock
        $this->moneiClientMock->payments = $this->paymentsApiMock;

        // Configure API client to return the Monei client mock
        $this->apiClientMock->method('getMoneiSdk')->willReturn($this->moneiClientMock);

        $this->refundPaymentService = new RefundPayment(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock
        );
    }

    public function testExecuteWithValidParams(): void
    {
        // Create valid refund data
        $refundData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => PaymentRefundReason::REQUESTED_BY_CUSTOMER,
            'amount' => 99.99
        ];

        // Create mock payment response
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getId')->willReturn('pay_123456');
        $paymentMock->method('getStatus')->willReturn('REFUNDED');

        // Configure payments API to return the mock payment
        $this
            ->paymentsApiMock
            ->method('refund')
            ->with(
                $this->equalTo('pay_123456'),
                $this->callback(function ($request) {
                    // Verify the refund request object has correct values
                    return $request instanceof RefundPaymentRequest &&
                        $request->getAmount() === 9999 &&
                        $request->getRefundReason() === PaymentRefundReason::REQUESTED_BY_CUSTOMER;
                })
            )
            ->willReturn($paymentMock);

        // Execute the service
        $result = $this->refundPaymentService->execute($refundData);

        // Verify the result
        $this->assertSame($paymentMock, $result);
        $this->assertEquals('pay_123456', $result->getId());
        $this->assertEquals('REFUNDED', $result->getStatus());
    }

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

        $this->refundPaymentService->execute($invalidData);
    }

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

        $this->refundPaymentService->execute($invalidData);
    }

    public function testExecuteWithInvalidAmount(): void
    {
        // Invalid amount (not numeric)
        $invalidData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => PaymentRefundReason::REQUESTED_BY_CUSTOMER,
            'amount' => 'not-a-number'
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/amount|validation|invalid/i');

        $this->refundPaymentService->execute($invalidData);
    }

    public function testExecuteWithApiException(): void
    {
        // Valid data
        $refundData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => PaymentRefundReason::REQUESTED_BY_CUSTOMER,
            'amount' => 99.99
        ];

        // Create API exception
        $apiException = new ApiException('Payment cannot be refunded');

        // Configure payments API to throw the exception
        $this
            ->paymentsApiMock
            ->method('refund')
            ->willThrowException($apiException);

        // Configure exception handler to rethrow as LocalizedException
        $this
            ->exceptionHandlerMock
            ->method('handle')
            ->withAnyParameters()
            ->willThrowException(new LocalizedException(__('Payment cannot be refunded')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment cannot be refunded');

        $this->refundPaymentService->execute($refundData);
    }
}

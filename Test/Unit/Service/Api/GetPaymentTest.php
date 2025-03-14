<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\ApiException;
use Monei\Model\Payment;
use Monei\MoneiClient;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\GetPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\PaymentsApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetPaymentTest extends TestCase
{
    /**
     * @var GetPayment
     */
    private GetPayment $getPaymentService;

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
        
        $this->getPaymentService = new GetPayment(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock
        );
    }
    
    public function testExecuteWithValidPaymentId(): void
    {
        // Create mock payment response
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getId')->willReturn('pay_123456');
        $paymentMock->method('getStatus')->willReturn('SUCCEEDED');
        
        // Configure payments API to return the mock payment
        $this->paymentsApiMock->method('get')
            ->with('pay_123456')
            ->willReturn($paymentMock);
        
        // Execute the service
        $result = $this->getPaymentService->execute('pay_123456');
        
        // Verify the result
        $this->assertSame($paymentMock, $result);
        $this->assertEquals('pay_123456', $result->getId());
        $this->assertEquals('SUCCEEDED', $result->getStatus());
    }
    
    public function testExecuteWithEmptyPaymentId(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment ID is required to retrieve payment details');
        
        $this->getPaymentService->execute('');
    }
    
    public function testExecuteWithApiException(): void
    {
        // Create API exception
        $apiException = new ApiException('Payment not found');
        
        // Configure payments API to throw the exception
        $this->paymentsApiMock->method('get')
            ->with('pay_invalid')
            ->willThrowException($apiException);
        
        // Configure exception handler to rethrow as LocalizedException
        $this->exceptionHandlerMock->method('handle')
            ->withAnyParameters()
            ->willThrowException(new LocalizedException(__('Payment not found')));
        
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment not found');
        
        $this->getPaymentService->execute('pay_invalid');
    }
}
<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Api\PaymentsApi;
use Monei\Model\Payment as MoneiPayment;
use Monei\Model\PaymentCancellationReason;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\CancelPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for CancelPayment API service
 *
 * @coversDefaultClass \Monei\MoneiPayment\Service\Api\CancelPayment
 */
class CancelPaymentTest extends TestCase
{
    /**
     * CancelPayment service instance
     *
     * @var CancelPayment
     */
    private CancelPayment $_cancelPaymentService;

    /**
     * Logger mock
     *
     * @var Logger|MockObject
     */
    private Logger $_loggerMock;

    /**
     * Exception handler mock
     *
     * @var ApiExceptionHandler|MockObject
     */
    private ApiExceptionHandler $_apiExceptionHandlerMock;

    /**
     * API client mock
     *
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $_apiClientMock;

    /**
     * MoneiClient mock
     *
     * @var MoneiClient|MockObject
     */
    private MoneiClient $_moneiClientMock;

    /**
     * PaymentsApi mock
     *
     * @var PaymentsApi|MockObject
     */
    private PaymentsApi $_paymentsApiMock;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_apiExceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        // Create mock for PaymentsApi
        $this->_paymentsApiMock = $this->createMock(PaymentsApi::class);

        // Create mock for MoneiClient with payments property
        $this->_moneiClientMock = $this->createMock(MoneiClient::class);
        // Set up the payments property to provide access to the PaymentsApi
        $this->_moneiClientMock->payments = $this->_paymentsApiMock;

        // Create API client mock that returns our MoneiClient mock
        $this->_apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->_apiClientMock->method('getMoneiSdk')->willReturn($this->_moneiClientMock);

        $this->_cancelPaymentService = new CancelPayment(
            $this->_loggerMock,
            $this->_apiExceptionHandlerMock,
            $this->_apiClientMock
        );
    }

    /**
     * Test execute with valid parameters
     */
    public function testExecuteWithValidParams(): void
    {
        $paymentId = 'pay_123456789';
        $cancellationReason = PaymentCancellationReason::REQUESTED_BY_CUSTOMER;
        $params = [
            'paymentId' => $paymentId,
            'cancellationReason' => $cancellationReason
        ];

        $paymentResponse = $this->createMock(MoneiPayment::class);
        $paymentResponse->method('getId')->willReturn($paymentId);
        $paymentResponse->method('getStatus')->willReturn('CANCELED');
        $paymentResponse->method('getCancellationReason')->willReturn($cancellationReason);

        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('cancel')
            ->with($paymentId, $this->anything())
            ->willReturn($paymentResponse);

        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('logApiRequest');

        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('logApiResponse');

        $result = $this->_cancelPaymentService->execute($params);

        $this->assertSame($paymentResponse, $result);
    }

    /**
     * Test execute with missing required parameter
     */
    public function testExecuteWithMissingRequiredParams(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Required parameter "payment_id" is missing or empty.');

        $params = [
            'cancellationReason' => PaymentCancellationReason::REQUESTED_BY_CUSTOMER
        ];

        $this
            ->_paymentsApiMock
            ->expects($this->never())
            ->method('cancel');

        $this->_cancelPaymentService->execute($params);
    }

    /**
     * Test execute with invalid cancellation reason
     */
    public function testExecuteWithInvalidCancellationReason(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Required parameter "cancellation_reason" is missing or empty.');

        $params = [
            'paymentId' => 'pay_123456789'
        ];

        $this
            ->_paymentsApiMock
            ->expects($this->never())
            ->method('cancel');

        $this->_cancelPaymentService->execute($params);
    }

    /**
     * Test execute with API exception
     */
    public function testExecuteWithApiException(): void
    {
        $paymentId = 'pay_123456789';
        $cancellationReason = PaymentCancellationReason::REQUESTED_BY_CUSTOMER;
        $params = [
            'paymentId' => $paymentId,
            'cancellationReason' => $cancellationReason
        ];

        $exception = new \Exception('API Error');

        // We won't set specific expectations for the error message since it's transformed internally
        $this->expectException(LocalizedException::class);

        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('cancel')
            ->with($paymentId, $this->anything())
            ->willThrowException($exception);

        $this->_cancelPaymentService->execute($params);
    }
}

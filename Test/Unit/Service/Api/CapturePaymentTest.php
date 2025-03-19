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
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\CapturePayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for CapturePayment API service
 *
 * @coversDefaultClass \Monei\MoneiPayment\Service\Api\CapturePayment
 */
class CapturePaymentTest extends TestCase
{
    /**
     * CapturePayment service instance
     *
     * @var CapturePayment
     */
    private CapturePayment $_capturePaymentService;

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

        $this->_capturePaymentService = new CapturePayment(
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
        $amount = 1000;
        $params = [
            'paymentId' => $paymentId,
            'amount' => $amount
        ];

        $paymentResponse = $this->createMock(MoneiPayment::class);
        $paymentResponse->method('getId')->willReturn($paymentId);
        $paymentResponse->method('getStatus')->willReturn('SUCCEEDED');
        $paymentResponse->method('getAmount')->willReturn($amount);

        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('capture')
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

        $result = $this->_capturePaymentService->execute($params);

        $this->assertSame($paymentResponse, $result);
    }

    /**
     * Test execute with missing required parameter
     */
    public function testExecuteWithMissingRequiredParams(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Required parameter "paymentId" is missing or empty.');

        $params = [
            'amount' => 1000
        ];

        $this
            ->_paymentsApiMock
            ->expects($this->never())
            ->method('capture');

        $this->_capturePaymentService->execute($params);
    }

    /**
     * Test execute with missing amount parameter
     */
    public function testExecuteWithMissingAmount(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Required parameter "amount" is missing or empty.');

        $paymentId = 'pay_123456789';
        $params = [
            'paymentId' => $paymentId
        ];

        $this
            ->_paymentsApiMock
            ->expects($this->never())
            ->method('capture');

        $this->_capturePaymentService->execute($params);
    }

    /**
     * Test execute with API exception
     */
    public function testExecuteWithApiException(): void
    {
        $paymentId = 'pay_123456789';
        $amount = 1000;
        $params = [
            'paymentId' => $paymentId,
            'amount' => $amount
        ];

        $exception = new \Exception('API Error');

        // We won't set specific expectations for the error message since it's transformed internally
        $this->expectException(LocalizedException::class);

        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('capture')
            ->with($paymentId, $this->anything())
            ->willThrowException($exception);

        $this->_capturePaymentService->execute($params);
    }
}

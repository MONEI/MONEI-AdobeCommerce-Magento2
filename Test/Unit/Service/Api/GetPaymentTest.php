<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Api\PaymentsApi;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\GetPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for GetPayment API service
 *
 * @category  Monei
 * @package   Monei\MoneiPayment\Test\Unit\Service\Api
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://monei.com/
 */
class GetPaymentTest extends TestCase
{
    /**
     * GetPayment service instance
     *
     * @var GetPayment
     */
    private GetPayment $_getPaymentService;

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
    private ApiExceptionHandler $_exceptionHandlerMock;

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
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        // Create mock for PaymentsApi
        $this->_paymentsApiMock = $this->createMock(PaymentsApi::class);

        // Create mock for MoneiClient with payments property
        $this->_moneiClientMock = $this->createMock(MoneiClient::class);
        // Set up the payments property to provide access to the PaymentsApi
        $this->_moneiClientMock->payments = $this->_paymentsApiMock;

        // Create API client mock that returns our MoneiClient mock
        $this->_apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->_apiClientMock->method('getMoneiSdk')->willReturn($this->_moneiClientMock);

        $this->_getPaymentService = new GetPayment(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock
        );
    }

    /**
     * Test with valid payment ID
     *
     * @return void
     */
    public function testExecuteWithValidPaymentId(): void
    {
        $paymentId = 'pay_123456';
        $expectedPayment = $this->createMock(MoneiPayment::class);
        $expectedPayment->method('getId')->willReturn($paymentId);
        $expectedPayment->method('getStatus')->willReturn('SUCCEEDED');
        $expectedPayment->method('getAmount')->willReturn(1000);
        $expectedPayment->method('getCurrency')->willReturn('EUR');

        // Configure PaymentsApi mock to return our payment
        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('get')
            ->with($paymentId)
            ->willReturn($expectedPayment);

        // Execute the service
        $result = $this->_getPaymentService->execute($paymentId);

        // Verify the result
        $this->assertSame($expectedPayment, $result);
        $this->assertEquals($paymentId, $result->getId());
        $this->assertEquals('SUCCEEDED', $result->getStatus());
        $this->assertEquals(1000, $result->getAmount());
        $this->assertEquals('EUR', $result->getCurrency());
    }

    /**
     * Test with empty payment ID
     *
     * @return void
     */
    public function testExecuteWithEmptyPaymentId(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment ID is required to retrieve payment details');

        $this->_getPaymentService->execute('');
    }

    /**
     * Test API error handling
     *
     * @return void
     */
    public function testExecuteWithApiError(): void
    {
        $paymentId = 'pay_123456';
        $apiException = new ApiException('API Error');

        // Configure PaymentsApi mock to throw an exception
        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('get')
            ->with($paymentId)
            ->willThrowException($apiException);

        // Configure exception handler
        $this
            ->_exceptionHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($apiException)
            ->willThrowException(new LocalizedException(__('API Error')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('API Error');

        $this->_getPaymentService->execute($paymentId);
    }
}

<?php

/**
 * Test case for ConfirmPayment service.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Api\PaymentsApi;
use Monei\Model\ConfirmPaymentRequest;
use Monei\Model\Payment;
use Monei\Model\PaymentBillingDetails;
use Monei\Model\PaymentCustomer;
use Monei\Model\PaymentShippingDetails;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\ConfirmPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ConfirmPayment.
 */
class ConfirmPaymentTest extends TestCase
{
    /**
     * @var ConfirmPayment
     */
    private $confirmPayment;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var ApiExceptionHandler|MockObject
     */
    private $apiExceptionHandlerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private $apiClientMock;

    /**
     * @var MoneiClient|MockObject
     */
    private $moneiClientMock;

    /**
     * @var PaymentsApi|MockObject
     */
    private $paymentsApiMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->apiExceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        $this->paymentsApiMock = $this->createMock(PaymentsApi::class);

        $this->moneiClientMock = $this->createMock(MoneiClient::class);
        $this->moneiClientMock->payments = $this->paymentsApiMock;

        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->apiClientMock->method('getMoneiSdk')->willReturn($this->moneiClientMock);

        $this->confirmPayment = new ConfirmPayment(
            $this->loggerMock,
            $this->apiExceptionHandlerMock,
            $this->apiClientMock
        );
    }

    /**
     * Test execute method with minimal parameters
     *
     * @return void
     */
    public function testExecuteWithMinimalParameters(): void
    {
        $paymentId = 'pay_123456';
        $paymentToken = 'token_123456';

        $data = [
            'payment_id' => $paymentId,
            'payment_token' => $paymentToken
        ];

        $paymentMock = $this->createMock(Payment::class);

        // Set expectation for the confirm call
        $this
            ->paymentsApiMock
            ->expects($this->once())
            ->method('confirm')
            ->willReturnCallback(function ($actualPaymentId, $confirmRequest) use ($paymentId, $paymentToken, $paymentMock) {
                $this->assertEquals($paymentId, $actualPaymentId);
                $this->assertInstanceOf(ConfirmPaymentRequest::class, $confirmRequest);
                $this->assertEquals($paymentToken, $confirmRequest->getPaymentToken());
                return $paymentMock;
            });

        $result = $this->confirmPayment->execute($data);

        $this->assertSame($paymentMock, $result);
    }

    /**
     * Test execute method with all parameters
     *
     * @return void
     */
    public function testExecuteWithAllParameters(): void
    {
        $paymentId = 'pay_123456';
        $paymentToken = 'token_123456';

        $data = [
            'payment_id' => $paymentId,
            'payment_token' => $paymentToken,
            'customer' => [
                'email' => 'test@example.com',
                'name' => 'Test Customer'
            ],
            'billing_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'ES',
                    'line1' => 'Test Street 123',
                    'postal_code' => '12345'
                ]
            ],
            'shipping_details' => [
                'address' => [
                    'city' => 'Shipping City',
                    'country' => 'ES',
                    'line1' => 'Shipping Street 123',
                    'postal_code' => '54321'
                ],
                'name' => 'Shipping Name'
            ],
            'metadata' => [
                'order_id' => '100000123'
            ]
        ];

        $paymentMock = $this->createMock(Payment::class);

        // Set expectation for the confirm call
        $this
            ->paymentsApiMock
            ->expects($this->once())
            ->method('confirm')
            ->willReturnCallback(function ($actualPaymentId, $confirmRequest) use ($paymentId, $paymentToken, $data, $paymentMock) {
                $this->assertEquals($paymentId, $actualPaymentId);
                $this->assertInstanceOf(ConfirmPaymentRequest::class, $confirmRequest);
                $this->assertEquals($paymentToken, $confirmRequest->getPaymentToken());

                // Check customer
                $customer = $confirmRequest->getCustomer();
                $this->assertInstanceOf(PaymentCustomer::class, $customer);
                $this->assertEquals($data['customer']['email'], $customer->getEmail());

                // We're just checking that the objects were created with our data
                // Not checking deep properties to avoid mocking too many layers
                $this->assertInstanceOf(PaymentBillingDetails::class, $confirmRequest->getBillingDetails());
                $this->assertInstanceOf(PaymentShippingDetails::class, $confirmRequest->getShippingDetails());

                // Check metadata
                $metadata = $confirmRequest->getMetadata();
                $this->assertEquals($data['metadata']['order_id'], $metadata['order_id']);

                return $paymentMock;
            });

        $result = $this->confirmPayment->execute($data);

        $this->assertSame($paymentMock, $result);
    }

    /**
     * Test execute method with camelCase parameters (that should be converted to snake_case)
     *
     * @return void
     */
    public function testExecuteWithCamelCaseParameters(): void
    {
        $paymentId = 'pay_123456';
        $paymentToken = 'token_123456';

        $data = [
            'paymentId' => $paymentId,
            'paymentToken' => $paymentToken,
            'customer' => [
                'email' => 'test@example.com'
            ]
        ];

        $paymentMock = $this->createMock(Payment::class);

        // Set expectation for the confirm call
        $this
            ->paymentsApiMock
            ->expects($this->once())
            ->method('confirm')
            ->willReturnCallback(function ($actualPaymentId, $confirmRequest) use ($paymentId, $paymentToken, $paymentMock) {
                $this->assertEquals($paymentId, $actualPaymentId);
                $this->assertInstanceOf(ConfirmPaymentRequest::class, $confirmRequest);
                $this->assertEquals($paymentToken, $confirmRequest->getPaymentToken());
                return $paymentMock;
            });

        $result = $this->confirmPayment->execute($data);

        $this->assertSame($paymentMock, $result);
    }

    /**
     * Test execute method with missing required parameters
     *
     * @return void
     */
    public function testExecuteWithMissingParameters(): void
    {
        $this->expectException(LocalizedException::class);

        $data = [
            'payment_id' => 'pay_123456'
            // Missing payment_token
        ];

        $this->confirmPayment->execute($data);
    }
}

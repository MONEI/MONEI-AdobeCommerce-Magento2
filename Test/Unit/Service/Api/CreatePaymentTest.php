<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Monei\Model\CreatePaymentRequest;
use Monei\Model\Payment;
use Monei\Model\PaymentTransactionType;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\CreatePayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;
use Monei\PaymentsApi;
use PHPUnit\Framework\TestCase;

class CreatePaymentTest extends TestCase
{
    /**
     * @var Logger|\PHPUnit\Framework\MockObject\MockObject
     */
    private Logger $loggerMock;

    /**
     * @var ApiExceptionHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    private ApiExceptionHandler $exceptionHandlerMock;

    /**
     * @var MoneiApiClient|\PHPUnit\Framework\MockObject\MockObject
     */
    private MoneiApiClient $apiClientMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private MoneiPaymentModuleConfigInterface $moduleConfigMock;

    /**
     * @var Url|\PHPUnit\Framework\MockObject\MockObject
     */
    private Url $urlBuilderMock;

    /**
     * @var CreatePayment
     */
    private CreatePayment $createPaymentService;

    /**
     * @var MoneiClient|\PHPUnit\Framework\MockObject\MockObject
     */
    private MoneiClient $moneiSdkMock;

    /**
     * @var PaymentsApi|\PHPUnit\Framework\MockObject\MockObject
     */
    private PaymentsApi $paymentsApiMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->urlBuilderMock = $this->createMock(Url::class);

        $this->moneiSdkMock = $this->createMock(MoneiClient::class);
        $this->paymentsApiMock = $this->createMock(PaymentsApi::class);
        $this->moneiSdkMock->payments = $this->paymentsApiMock;

        $this->apiClientMock->method('getMoneiSdk')->willReturn($this->moneiSdkMock);

        $this->createPaymentService = new CreatePayment(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock,
            $this->moduleConfigMock,
            $this->urlBuilderMock
        );
    }

    public function testExecuteWithMinimalParams(): void
    {
        // Set up expected URL callbacks
        $this->urlBuilderMock->method('getUrl')->willReturnMap([
            ['monei/payment/complete', [], 'https://example.com/monei/payment/complete'],
            ['monei/payment/callback', [], 'https://example.com/monei/payment/callback'],
            ['monei/payment/cancel', [], 'https://example.com/monei/payment/cancel'],
        ]);

        // Set up module config
        $this->moduleConfigMock->method('getTypeOfPayment')->willReturn(TypeOfPayment::TYPE_AUTHORIZED);

        // Create mock payment response
        $paymentMock = $this->createMock(Payment::class);

        // Set up payment API to return our mock
        $this
            ->paymentsApiMock
            ->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf(CreatePaymentRequest::class))
            ->willReturn($paymentMock);

        // Call the method with minimal parameters
        $result = $this->createPaymentService->execute([
            'amount' => 1000,
            'currency' => 'EUR',
            'order_id' => '000000123',
            'shipping_details' => [
                'address' => [
                    'city' => 'Example City',
                    'country' => 'ES',
                    'line1' => '123 Example St',
                    'postal_code' => '12345',
                ]
            ],
        ]);

        // Verify the result is our mock
        $this->assertSame($paymentMock, $result);
    }

    public function testExecuteWithAllParams(): void
    {
        // Set up expected URL callbacks
        $this->urlBuilderMock->method('getUrl')->willReturnMap([
            ['monei/payment/complete', [], 'https://example.com/monei/payment/complete'],
            ['monei/payment/callback', [], 'https://example.com/monei/payment/callback'],
            ['monei/payment/cancel', [], 'https://example.com/monei/payment/cancel'],
        ]);

        // Set up module config for pre-authorized payments
        $this->moduleConfigMock->method('getTypeOfPayment')->willReturn(TypeOfPayment::TYPE_PRE_AUTHORIZED);

        // Create mock payment response
        $paymentMock = $this->createMock(Payment::class);

        // Set up payment API to return our mock and capture the request
        $capturedRequest = null;
        $this
            ->paymentsApiMock
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (CreatePaymentRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return true;
            }))
            ->willReturn($paymentMock);

        // Call the method with all parameters
        $result = $this->createPaymentService->execute([
            'amount' => 1000,
            'currency' => 'EUR',
            'order_id' => '000000123',
            'allowed_payment_methods' => ['card', 'googlepay'],
            'payment_token' => 'token_123',
            'customer' => [
                'email' => 'customer@example.com',
                'name' => 'John Doe',
                'phone' => '+1234567890',
            ],
            'billing_details' => [
                'address' => [
                    'city' => 'Billing City',
                    'country' => 'ES',
                    'line1' => '456 Billing St',
                    'postal_code' => '54321',
                ]
            ],
            'shipping_details' => [
                'address' => [
                    'city' => 'Shipping City',
                    'country' => 'ES',
                    'line1' => '789 Shipping St',
                    'postal_code' => '98765',
                ]
            ],
            'description' => 'Test order',
            'metadata' => [
                'custom_field' => 'custom_value',
            ],
        ]);

        // Verify the result is our mock
        $this->assertSame($paymentMock, $result);

        // Verify the request parameters
        $this->assertEquals(1000, $capturedRequest->getAmount());
        $this->assertEquals('EUR', $capturedRequest->getCurrency());
        $this->assertEquals('000000123', $capturedRequest->getOrderId());
        $this->assertEquals(['card', 'googlepay'], $capturedRequest->getAllowedPaymentMethods());
        $this->assertEquals('token_123', $capturedRequest->getPaymentToken());
        $this->assertEquals('Test order', $capturedRequest->getDescription());
        $this->assertEquals(['custom_field' => 'custom_value'], $capturedRequest->getMetadata());
        $this->assertEquals(PaymentTransactionType::AUTH, $capturedRequest->getTransactionType());
    }

    public function testValidateParamsThrowsExceptionWithMissingParams(): void
    {
        $this->expectException(LocalizedException::class);

        // Call the method without required parameters
        $this->createPaymentService->execute([
            'amount' => 1000,
            'currency' => 'EUR',
            // Missing order_id
            // Missing shipping_details
        ]);
    }
}

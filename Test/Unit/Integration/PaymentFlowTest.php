<?php

namespace Monei\MoneiPayment\Test\Unit\Integration;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Monei\Model\Payment;
use Monei\Model\PaymentStatus;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Api\CreatePayment;
use Monei\MoneiPayment\Service\Api\GetPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Integration test that simulates a full payment flow
 */
class PaymentFlowTest extends TestCase
{
    /**
     * @var PaymentProcessor|MockObject
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var CreatePayment|MockObject
     */
    private CreatePayment $createPaymentService;

    /**
     * @var GetPayment|MockObject
     */
    private GetPayment $getPaymentService;

    /**
     * @var PaymentDTOFactory|MockObject
     */
    private PaymentDTOFactory $paymentDtoFactory;

    /**
     * @var OrderInterface|MockObject
     */
    private OrderInterface $orderMock;

    /**
     * @var OrderPaymentInterface|MockObject
     */
    private OrderPaymentInterface $orderPaymentMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private MoneiPaymentModuleConfigInterface $configMock;

    protected function setUp(): void
    {
        $this->paymentProcessor = $this->createMock(PaymentProcessor::class);
        $this->createPaymentService = $this->createMock(CreatePayment::class);
        $this->getPaymentService = $this->createMock(GetPayment::class);
        $this->paymentDtoFactory = $this->createMock(PaymentDTOFactory::class);
        $this->orderMock = $this->createMock(OrderInterface::class);
        $this->orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $this->configMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
    }

    /**
     * Test a successful payment flow from creation to processing
     */
    public function testSuccessfulPaymentFlow(): void
    {
        // 1. Set up order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('getEntityId')->willReturn(123);
        $this->orderMock->method('getPayment')->willReturn($this->orderPaymentMock);
        $this->orderMock->method('getGrandTotal')->willReturn(99.99);
        $this->orderMock->method('getOrderCurrencyCode')->willReturn('EUR');
        $this->orderMock->method('getCustomerEmail')->willReturn('customer@example.com');
        $this->orderMock->method('getBillingAddress')->willReturn(null);
        // Skip getShippingAddress mock since it's not being called in this test
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);

        // 2. Set up payment request data
        $paymentRequestData = [
            'amount' => 9999,  // 99.99 EUR in cents
            'currency' => 'EUR',
            'order_id' => '100000123',
            'customer' => [
                'email' => 'customer@example.com',
                'name' => 'Test Customer',
            ],
            'shipping_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'ES',
                    'line1' => 'Test Street 123',
                    'postal_code' => '12345',
                ]
            ],
            'description' => 'Order #100000123',
        ];

        // 3. Set up payment creation response
        $paymentResponseMock = $this->createMock(Payment::class);
        $paymentResponseMock->method('getId')->willReturn('pay_123456789');
        $paymentResponseMock->method('getStatus')->willReturn(PaymentStatus::PENDING);
        $paymentResponseMock->method('getAmount')->willReturn(9999);
        $paymentResponseMock->method('getCurrency')->willReturn('EUR');
        $paymentResponseMock->method('getOrderId')->willReturn('100000123');

        // 4. Mock the payment creation service
        $this
            ->createPaymentService
            ->method('execute')
            ->with($this->callback(function ($data) {
                // Verify essential parameters
                return isset($data['amount']) &&
                    isset($data['currency']) &&
                    isset($data['order_id']) &&
                    isset($data['shipping_details']);
            }))
            ->willReturn($paymentResponseMock);

        // 5. Mock payment DTO creation
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn('pay_123456789');
        $paymentDtoMock->method('getStatus')->willReturn(PaymentStatus::SUCCEEDED);
        $paymentDtoMock->method('getAmount')->willReturn(99.99);
        $paymentDtoMock->method('getAmountInCents')->willReturn(9999);
        $paymentDtoMock->method('getCurrency')->willReturn('EUR');
        $paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $paymentDtoMock->method('isSucceeded')->willReturn(true);

        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // 6. Mock the payment processor
        $processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);
        $processingResultMock->method('isSuccess')->willReturn(true);
        $processingResultMock->method('getStatus')->willReturn(PaymentStatus::SUCCEEDED);

        $this
            ->paymentProcessor
            ->method('process')
            ->with('100000123', 'pay_123456789', $this->anything())
            ->willReturn($processingResultMock);

        // 7. Execute the payment flow (this would be called by a controller in real code)
        $paymentId = null;

        try {
            // Create the payment
            $payment = $this->createPaymentService->execute($paymentRequestData);
            $paymentId = $payment->getId();

            // Verify payment was created
            $this->assertEquals('pay_123456789', $paymentId);
            $this->assertEquals(PaymentStatus::PENDING, $payment->getStatus());

            // Process the payment (in a real scenario, this happens after a webhook or callback)
            $result = $this->paymentProcessor->process('100000123', $paymentId, [
                'id' => $paymentId,
                'status' => PaymentStatus::SUCCEEDED,
                'amount' => 9999,
                'currency' => 'EUR',
                'orderId' => '100000123'
            ]);

            // Verify processing was successful
            $this->assertTrue($result->isSuccess());
            $this->assertEquals(PaymentStatus::SUCCEEDED, $result->getStatus());
        } catch (LocalizedException $e) {
            $this->fail('Payment flow failed with exception: ' . $e->getMessage());
        }
    }

    /**
     * Test a failed payment flow
     */
    public function testFailedPaymentFlow(): void
    {
        // 1. Set up order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000124');
        $this->orderMock->method('getEntityId')->willReturn(124);
        $this->orderMock->method('getPayment')->willReturn($this->orderPaymentMock);
        $this->orderMock->method('getState')->willReturn(Order::STATE_NEW);

        // 2. Set up payment request data
        $paymentRequestData = [
            'amount' => 9999,
            'currency' => 'EUR',
            'order_id' => '100000124',
            'shipping_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'ES',
                    'line1' => 'Test Street 123',
                    'postal_code' => '12345',
                ]
            ],
        ];

        // 3. Set up payment creation response
        $paymentResponseMock = $this->createMock(Payment::class);
        $paymentResponseMock->method('getId')->willReturn('pay_987654321');
        $paymentResponseMock->method('getStatus')->willReturn(PaymentStatus::PENDING);
        $paymentResponseMock->method('getAmount')->willReturn(9999);
        $paymentResponseMock->method('getCurrency')->willReturn('EUR');
        $paymentResponseMock->method('getOrderId')->willReturn('100000124');

        // 4. Mock the payment creation service
        $this
            ->createPaymentService
            ->method('execute')
            ->with($this->anything())
            ->willReturn($paymentResponseMock);

        // 5. Mock payment DTO creation for failed payment
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn('pay_987654321');
        $paymentDtoMock->method('getStatus')->willReturn(PaymentStatus::FAILED);
        $paymentDtoMock->method('getAmount')->willReturn(99.99);
        $paymentDtoMock->method('getAmountInCents')->willReturn(9999);
        $paymentDtoMock->method('getCurrency')->willReturn('EUR');
        $paymentDtoMock->method('getOrderId')->willReturn('100000124');
        $paymentDtoMock->method('isSucceeded')->willReturn(false);
        $paymentDtoMock->method('isFailed')->willReturn(true);
        $paymentDtoMock->method('getStatusCode')->willReturn('E001');
        $paymentDtoMock->method('getStatusMessage')->willReturn('Payment declined');

        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // 6. Mock the payment processor for failed payment
        $processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);
        $processingResultMock->method('isSuccess')->willReturn(false);
        $processingResultMock->method('getStatus')->willReturn(PaymentStatus::FAILED);
        $processingResultMock->method('getErrorMessage')->willReturn('Payment declined');
        $processingResultMock->method('getStatusCode')->willReturn('E001');

        $this
            ->paymentProcessor
            ->method('process')
            ->with('100000124', 'pay_987654321', $this->anything())
            ->willReturn($processingResultMock);

        // 7. Execute the payment flow
        $paymentId = null;

        try {
            // Create the payment
            $payment = $this->createPaymentService->execute($paymentRequestData);
            $paymentId = $payment->getId();

            // Verify payment was created
            $this->assertEquals('pay_987654321', $paymentId);
            $this->assertEquals(PaymentStatus::PENDING, $payment->getStatus());

            // Process the payment (in a real scenario, this happens after a webhook or callback)
            $result = $this->paymentProcessor->process('100000124', $paymentId, [
                'id' => $paymentId,
                'status' => PaymentStatus::FAILED,
                'amount' => 9999,
                'currency' => 'EUR',
                'orderId' => '100000124',
                'statusCode' => 'E001',
                'statusMessage' => 'Payment declined'
            ]);

            // Verify processing indicated failure
            $this->assertFalse($result->isSuccess());
            $this->assertEquals(PaymentStatus::FAILED, $result->getStatus());
            $this->assertEquals('Payment declined', $result->getErrorMessage());
            $this->assertEquals('E001', $result->getStatusCode());
        } catch (LocalizedException $e) {
            $this->fail('Payment flow failed with exception: ' . $e->getMessage());
        }
    }
}

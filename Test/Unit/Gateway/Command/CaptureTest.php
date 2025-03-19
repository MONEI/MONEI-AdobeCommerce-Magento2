<?php

/**
 * Test case for Capture Gateway Command.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Monei\MoneiPayment\Gateway\Command\Capture;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Capture Gateway Command.
 *
 * @license  https://opensource.org/license/mit/ MIT License
 * @link     https://monei.com/
 */
class CaptureTest extends TestCase
{
    /**
     * Capture command instance being tested
     *
     * @var Capture
     */
    private $_command;

    /**
     * Mock of CapturePaymentInterface
     *
     * @var CapturePaymentInterface|MockObject
     */
    private $_capturePaymentServiceMock;

    /**
     * Mock of Logger
     *
     * @var Logger|MockObject
     */
    private $_loggerMock;

    /**
     * Mock of Payment
     *
     * @var Payment|MockObject
     */
    private $_paymentMock;

    /**
     * Mock of Order
     *
     * @var Order|MockObject
     */
    private $_orderMock;

    /**
     * Mock of PaymentDataObjectInterface
     *
     * @var PaymentDataObjectInterface|MockObject
     */
    private $_paymentDOMock;

    /**
     * Mock of InvoiceInterface
     *
     * @var InvoiceInterface|MockObject
     */
    private $_invoiceMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_capturePaymentServiceMock = $this->createMock(CapturePaymentInterface::class);
        $this->_loggerMock = $this->createMock(Logger::class);

        $this->_paymentMock = $this->createMock(Payment::class);
        $this->_orderMock = $this->createMock(Order::class);
        $this->_invoiceMock = $this->createMock(InvoiceInterface::class);

        $this->_paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $this->_paymentDOMock->method('getPayment')->willReturn($this->_paymentMock);

        $this->_paymentMock->method('getOrder')->willReturn($this->_orderMock);

        $this->_command = new Capture(
            $this->_capturePaymentServiceMock,
            $this->_loggerMock
        );
    }

    /**
     * Test successful capture execution
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $paymentId = 'pay_123456789';
        $amount = 100.0;
        $captureId = 'cap_987654321';

        // Set up payment mock with additional information
        $this
            ->_paymentMock
            ->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) use ($paymentId) {
                $map = [
                    'monei_payment_status' => null,
                    'monei_is_captured' => false,
                    'monei_payment_id' => $paymentId
                ];
                return $map[$key] ?? null;
            });

        $this
            ->_paymentMock
            ->method('getLastTransId')
            ->willReturn($paymentId);

        // Set up willReturnMap for getData on the order mock
        $this
            ->_orderMock
            ->method('getData')
            ->willReturnCallback(function ($param) {
                if ($param === MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID) {
                    return '';
                }
                if ($param === 'monei_status') {
                    return null;
                }
                return null;
            });

        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        // Set up response mock
        $responseMock = $this->createMock(MoneiPayment::class);
        $responseMock->method('getId')->willReturn($captureId);

        // Set up capture service mock
        $this
            ->_capturePaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'paymentId' => $paymentId,
                'amount' => $amount
            ])
            ->willReturn($responseMock);

        // Payment mock should be updated with transaction data
        $this
            ->_paymentMock
            ->expects($this->once())
            ->method('setTransactionId')
            ->with($captureId);
        $this
            ->_paymentMock
            ->expects($this->once())
            ->method('setLastTransId')
            ->with($captureId);
        $this
            ->_paymentMock
            ->expects($this->exactly(2))
            ->method('setAdditionalInformation')
            ->withConsecutive(
                [PaymentInfoInterface::PAYMENT_IS_CAPTURED, true],
                [PaymentInfoInterface::PAYMENT_STATUS, Status::SUCCEEDED]
            );

        // Execute the command
        $result = $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => $amount
        ]);

        $this->assertNull($result);
    }

    /**
     * Test capture when payment is already captured
     *
     * @return void
     */
    public function testExecuteAlreadyCaptured(): void
    {
        // Set up payment mock with additional information indicating it's already captured
        $this
            ->_paymentMock
            ->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) {
                $map = [
                    'monei_payment_status' => Status::SUCCEEDED,
                    'monei_is_captured' => true,
                    'monei_payment_id' => 'pay_123456789'
                ];
                return $map[$key] ?? null;
            });

        $this
            ->_orderMock
            ->method('getData')
            ->willReturnCallback(function ($param) {
                if ($param === 'monei_status') {
                    return Status::SUCCEEDED;
                }
                return null;
            });

        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        // The capture service should not be called
        $this
            ->_capturePaymentServiceMock
            ->expects($this->never())
            ->method('execute');

        // Execute the command
        $result = $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => 100.0
        ]);

        $this->assertNull($result);
    }

    /**
     * Test capture with missing payment ID
     *
     * @return void
     */
    public function testExecuteWithMissingPaymentId(): void
    {
        // Set up payment mock with empty payment ID
        $this
            ->_paymentMock
            ->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) {
                $map = [
                    'monei_payment_status' => null,
                    'monei_is_captured' => false,
                    'monei_payment_id' => null
                ];
                return $map[$key] ?? null;
            });

        // Order also has no payment ID
        $this
            ->_orderMock
            ->method('getData')
            ->willReturnCallback(function ($param) {
                return null;  // Return null for any parameter
            });

        // Last transaction ID is empty too
        $this
            ->_paymentMock
            ->method('getLastTransId')
            ->willReturn('');

        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cannot capture payment: Missing payment ID');

        // Execute the command
        $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => 100.0
        ]);
    }

    /**
     * Test capture when payment service throws an exception
     *
     * @return void
     */
    public function testExecuteWithServiceException(): void
    {
        // Set up payment mock with payment ID
        $paymentId = 'pay_123456789';
        $amount = 100.0;

        $this
            ->_paymentMock
            ->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) use ($paymentId) {
                $map = [
                    'monei_payment_id' => $paymentId
                ];
                return $map[$key] ?? null;
            });

        $this
            ->_paymentMock
            ->method('getLastTransId')
            ->willReturn($paymentId);

        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        // The capture service should throw an exception
        $this
            ->_capturePaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'paymentId' => $paymentId,
                'amount' => $amount
            ])
            ->willThrowException(new \Exception('API Error'));

        // Expected exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error capturing payment: API Error');

        // Execute the command
        $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => $amount
        ]);
    }

    /**
     * Test capture with invoice updating
     *
     * @return void
     */
    public function testExecuteWithInvoiceUpdate(): void
    {
        $paymentId = 'pay_123456789';
        $amount = 100.0;
        $captureId = 'cap_987654321';

        // Set up payment mock with additional information
        $this
            ->_paymentMock
            ->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) use ($paymentId) {
                $map = [
                    'monei_payment_status' => null,
                    'monei_is_captured' => false,
                    'monei_payment_id' => $paymentId
                ];
                return $map[$key] ?? null;
            });

        $this
            ->_paymentMock
            ->method('getLastTransId')
            ->willReturn($paymentId);

        $this
            ->_orderMock
            ->method('getData')
            ->willReturnCallback(function ($param) {
                return null;  // Return null for any parameter
            });

        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        // Set up response mock
        $responseMock = $this->createMock(MoneiPayment::class);
        $responseMock->method('getId')->willReturn($captureId);

        // Set up capture service mock
        $this
            ->_capturePaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn($responseMock);

        // Set up invoice
        $this
            ->_invoiceMock
            ->method('getIncrementId')
            ->willReturn('INV100000001');

        // Create a PaymentDataObjectInterface with additional functionality
        $testPaymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $testPaymentDOMock->method('getPayment')->willReturn($this->_paymentMock);

        // Add a separate method for the created invoice
        $paymentDO = new class($this->_paymentMock, $this->_invoiceMock) implements PaymentDataObjectInterface {
            private $_payment;
            private $_invoice;

            public function __construct($payment, $invoice)
            {
                $this->_payment = $payment;
                $this->_invoice = $invoice;
            }

            public function getPayment()
            {
                return $this->_payment;
            }

            public function getOrder()
            {
                return null;
            }

            public function getCreatedInvoice()
            {
                return $this->_invoice;
            }
        };

        // Invoice should be updated with transaction ID
        $this
            ->_invoiceMock
            ->expects($this->once())
            ->method('setTransactionId')
            ->with($captureId);

        // Execute the command
        $result = $this->_command->execute([
            'payment' => $paymentDO,
            'amount' => $amount
        ]);

        $this->assertNull($result);
    }
}

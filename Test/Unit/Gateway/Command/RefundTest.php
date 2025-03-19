<?php

/**
 * Test case for Refund Gateway Command.
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
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Monei\MoneiPayment\Gateway\Command\Refund;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Refund Gateway Command.
 *
 * @license  https://opensource.org/license/mit/ MIT License
 * @link     https://monei.com/
 */
class RefundTest extends TestCase
{
    /**
     * Refund command instance being tested
     *
     * @var Refund
     */
    private $_command;

    /**
     * Mock of RefundPaymentInterface
     *
     * @var RefundPaymentInterface|MockObject
     */
    private $_refundPaymentServiceMock;

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
     * Mock of Creditmemo
     *
     * @var Creditmemo|MockObject
     */
    private $_creditmemoMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_refundPaymentServiceMock = $this->createMock(RefundPaymentInterface::class);
        $this->_loggerMock = $this->createMock(Logger::class);

        $this->_paymentMock = $this->createMock(Payment::class);
        $this->_orderMock = $this->createMock(Order::class);
        $this->_creditmemoMock = $this->createMock(Creditmemo::class);

        $this->_paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $this->_paymentDOMock->method('getPayment')->willReturn($this->_paymentMock);

        $this->_paymentMock->method('getOrder')->willReturn($this->_orderMock);
        $this->_paymentMock->method('getCreditmemo')->willReturn($this->_creditmemoMock);

        $this->_command = new Refund(
            $this->_refundPaymentServiceMock,
            $this->_loggerMock
        );
    }

    /**
     * Test successful refund execution
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $paymentId = 'pay_123456789';
        $amount = 100.0;
        $refundId = 'ref_987654321';
        $storeId = 1;
        $reason = 'requested_by_customer';

        // Set up order mock
        $this
            ->_orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn($paymentId);
        $this
            ->_orderMock
            ->method('getStoreId')
            ->willReturn($storeId);
        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');
        $this
            ->_orderMock
            ->method('getBaseGrandTotal')
            ->willReturn(100.0);

        // Set up credit memo mock
        $this
            ->_creditmemoMock
            ->method('getData')
            ->with('refund_reason')
            ->willReturn($reason);
        $this
            ->_creditmemoMock
            ->method('getBaseGrandTotal')
            ->willReturn(100.0);

        // Set up response mock - need to use the real SDK class
        $responseMock = $this->createMock(MoneiPayment::class);
        $responseMock->method('getId')->willReturn($refundId);

        // Set up refund service mock
        $this
            ->_refundPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'paymentId' => $paymentId,
                'amount' => $amount,
                'refundReason' => $reason,
                'storeId' => $storeId,
            ])
            ->willReturn($responseMock);

        // Payment mock should be updated with transaction data
        $this
            ->_paymentMock
            ->expects($this->once())
            ->method('setTransactionId')
            ->with($refundId);
        $this
            ->_paymentMock
            ->expects($this->once())
            ->method('setLastTransId')
            ->with($refundId);
        $this
            ->_paymentMock
            ->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(
                PaymentInfoInterface::PAYMENT_STATUS,
                Status::REFUNDED
            );

        // Execute the command
        $result = $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => $amount
        ]);

        $this->assertNull($result);
    }

    /**
     * Test refund with missing payment ID
     *
     * @return void
     */
    public function testExecuteWithMissingPaymentId(): void
    {
        // Set up order mock to return empty payment ID
        $this
            ->_orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn('');
        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        // Payment additional info is also empty
        $this
            ->_paymentMock
            ->method('getAdditionalInformation')
            ->with(PaymentInfoInterface::PAYMENT_ID)
            ->willReturn('');

        // Last transaction ID is empty too
        $this
            ->_paymentMock
            ->method('getLastTransId')
            ->willReturn('');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cannot process refund: Monei payment ID is missing.');

        // Execute the command
        $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => 100.0
        ]);
    }

    /**
     * Test refund with exception from service
     *
     * @return void
     */
    public function testExecuteWithServiceException(): void
    {
        $paymentId = 'pay_123456789';
        $amount = 100.0;
        $storeId = 1;

        // Set up order mock
        $this
            ->_orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn($paymentId);
        $this
            ->_orderMock
            ->method('getStoreId')
            ->willReturn($storeId);
        $this
            ->_orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        // Service throws exception
        $this
            ->_refundPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('API Error'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error processing refund: API Error');

        // Execute the command
        $this->_command->execute([
            'payment' => $this->_paymentDOMock,
            'amount' => $amount
        ]);
    }
}

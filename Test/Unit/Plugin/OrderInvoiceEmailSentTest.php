<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\Test\Unit\Helper\Config;
use Magento\Framework\Test\Unit\Helper\Logger;
use Magento\Framework\Test\Unit\Helper\ObjectManager;
use Magento\Framework\Test\Unit\Helper\ReflectionHelper;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\OrderInvoiceEmailSent;
use Monei\MoneiPayment\Service\Logger as MoneiLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderInvoiceEmailSentTest extends TestCase
{
    /**
     * @var MoneiLogger|MockObject
     */
    private $loggerMock;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var OrderInvoiceEmailSent
     */
    private $plugin;

    /**
     * @var MockObject
     */
    private $orderMock;

    /**
     * @var MockObject
     */
    private $invoiceMock;

    /**
     * @var MockObject
     */
    private $paymentMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(MoneiLogger::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->orderMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getIncrementId', 'getStatusHistories', 'getState', 'getStatus'])
            ->getMock();
        $this->invoiceMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder', 'getOrderId', 'getIncrementId', 'getEmailSent'])
            ->getMock();
        $this->paymentMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();

        // Common setup for all tests
        $this
            ->invoiceMock
            ->method('getIncrementId')
            ->willReturn('INV-123');

        $this
            ->invoiceMock
            ->method('getOrder')
            ->willReturn($this->orderMock);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('000000123');

        $this->plugin = new OrderInvoiceEmailSent(
            $this->loggerMock,
            $this->orderRepositoryMock
        );
    }

    /**
     * Test with non-Monei payment method
     */
    public function testAfterSendWithNonMoneiPayment()
    {
        $orderId = 123;

        $this
            ->invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);

        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($this->orderMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this
            ->paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('not_monei');

        $result = $this->plugin->afterSend(
            $this->createMock(InvoiceSender::class),
            true,
            $this->invoiceMock
        );

        $this->assertTrue($result);
    }

    /**
     * Test with Monei payment but email was not sent
     */
    public function testAfterSendWithMoneiPaymentEmailNotSent()
    {
        $orderId = 123;
        $orderIncrementId = '000000123';
        $invoiceIncrementId = 'INV-123';

        $this
            ->invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);

        $this
            ->invoiceMock
            ->expects($this->atLeastOnce())
            ->method('getIncrementId')
            ->willReturn($invoiceIncrementId);

        $this
            ->invoiceMock
            ->expects($this->once())
            ->method('getEmailSent')
            ->willReturn(false);

        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($this->orderMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);

        $this
            ->orderMock
            ->expects($this->once())
            ->method('getState')
            ->willReturn('processing');

        $this
            ->paymentMock
            ->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn('monei');

        $this
            ->loggerMock
            ->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                '[InvoiceEmail] Email not sent',
                [
                    'order_id' => $orderIncrementId,
                    'invoice_id' => $invoiceIncrementId,
                    'email_sent' => 'No',
                    'order_state' => 'processing'
                ]
            );

        $result = $this->plugin->afterSend(
            $this->createMock(InvoiceSender::class),
            false,
            $this->invoiceMock
        );

        $this->assertFalse($result);
    }

    /**
     * Test with Monei payment, email sent but no history entries
     */
    public function testAfterSendWithMoneiPaymentNoHistory()
    {
        $orderId = 123;
        $orderIncrementId = '000000123';
        $invoiceIncrementId = 'INV-123';

        $this
            ->invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);

        $this
            ->invoiceMock
            ->expects($this->atLeastOnce())
            ->method('getIncrementId')
            ->willReturn($invoiceIncrementId);

        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($this->orderMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);

        $this
            ->orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn(null);

        $this
            ->paymentMock
            ->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn('monei');

        $this
            ->loggerMock
            ->expects($this->atLeastOnce())
            ->method('debug');

        $result = $this->plugin->afterSend(
            $this->createMock(InvoiceSender::class),
            true,
            $this->invoiceMock
        );

        $this->assertTrue($result);
    }

    /**
     * Test with Monei payment, email sent and relevant history entries
     */
    public function testAfterSendWithMoneiPaymentRelevantHistory()
    {
        $orderId = 123;
        $orderIncrementId = '000000123';
        $invoiceIncrementId = 'INV-123';
        $orderStatus = 'processing';

        // Create a more accurately mocked historyEntry
        $historyMethods = [
            'getIsCustomerNotified', 'getStatus', 'getComment',
            'setIsCustomerNotified', 'getEntityName', 'getCreatedAt'
        ];

        $historyEntry = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Status\History::class)
            ->disableOriginalConstructor()
            ->onlyMethods($historyMethods)
            ->getMock();

        $historyEntry
            ->expects($this->atLeastOnce())
            ->method('getIsCustomerNotified')
            ->willReturn(false);

        $historyEntry
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $historyEntry
            ->expects($this->atLeastOnce())
            ->method('getComment')
            ->willReturn('Captured amount of $100.00');

        $historyEntry
            ->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true);

        $historyEntry
            ->expects($this->any())
            ->method('getEntityName')
            ->willReturn('invoice');

        $historyEntry
            ->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn('2023-01-01 00:00:00');

        $historyEntries = [$historyEntry];

        $this
            ->invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);

        $this
            ->invoiceMock
            ->expects($this->atLeastOnce())
            ->method('getIncrementId')
            ->willReturn($invoiceIncrementId);

        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($this->orderMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);

        $this
            ->orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn($historyEntries);

        $this
            ->orderMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $this
            ->paymentMock
            ->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn('monei');

        $this
            ->loggerMock
            ->expects($this->atLeastOnce())
            ->method('debug');

        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->orderMock);

        $result = $this->plugin->afterSend(
            $this->createMock(InvoiceSender::class),
            true,
            $this->invoiceMock
        );

        $this->assertTrue($result);
    }

    /**
     * Test with exception handling
     */
    public function testAfterSendWithException()
    {
        $orderId = 123;
        $orderIncrementId = '000000123';
        $exception = new \Exception('Test exception');

        $this
            ->invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willThrowException($exception);

        $result = $this->plugin->afterSend(
            $this->createMock(InvoiceSender::class),
            true,
            $this->invoiceMock
        );

        $this->assertTrue($result);
    }
}

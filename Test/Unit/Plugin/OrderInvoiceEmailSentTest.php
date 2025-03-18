<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\OrderInvoiceEmailSent;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderInvoiceEmailSentTest extends TestCase
{
    /**
     * @var Logger|MockObject
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

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
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
        // Create a mock invoice
        $invoiceMock = $this->createMock(InvoiceInterface::class);
        $invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn(1);

        // Create invoice sender mock
        $invoiceSenderMock = $this->createMock(InvoiceSender::class);

        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock with non-Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('checkmo');

        // Set up order mock to return payment mock
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        // Set up order repository to return the order
        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($orderMock);

        // Execute the plugin method with a successful email send result
        $result = $this->plugin->afterSend($invoiceSenderMock, true, $invoiceMock);

        // Assert that the result is unchanged
        $this->assertTrue($result);
    }

    /**
     * Test with Monei payment but email was not sent
     */
    public function testAfterSendWithMoneiPaymentEmailNotSent()
    {
        $orderId = 1;
        $orderIncrementId = '100000001';
        $invoiceIncrementId = '100000001-1';

        // Create a mock invoice
        $invoiceMock = $this->createMock(InvoiceInterface::class);
        $invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);
        $invoiceMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($invoiceIncrementId);

        // Create invoice sender mock
        $invoiceSenderMock = $this->createMock(InvoiceSender::class);

        // Create order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Set up order mock to return payment mock
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        // Set up order repository to return the order
        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        // Logger should be called for debug logging
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with(sprintf(
                '[Invoice email not sent] Order %s, Invoice %s - Skipping history update',
                $orderIncrementId,
                $invoiceIncrementId
            ));

        // Execute the plugin method with a failed email send result
        $result = $this->plugin->afterSend($invoiceSenderMock, false, $invoiceMock);

        // Assert that the result is unchanged
        $this->assertFalse($result);
    }

    /**
     * Test with Monei payment, email sent but no history entries
     */
    public function testAfterSendWithMoneiPaymentNoHistory()
    {
        $orderId = 1;
        $orderIncrementId = '100000001';
        $invoiceIncrementId = '100000001-1';

        // Create a mock invoice
        $invoiceMock = $this->createMock(InvoiceInterface::class);
        $invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);
        $invoiceMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($invoiceIncrementId);

        // Create invoice sender mock
        $invoiceSenderMock = $this->createMock(InvoiceSender::class);

        // Create order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Set up order mock to return payment mock and empty history
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);
        $orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn([]);

        // Set up order repository to return the order
        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        // Logger should be called once for debug logging
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with(sprintf(
                '[Invoice email sent] Order %s, Invoice %s - Updating history entries',
                $orderIncrementId,
                $invoiceIncrementId
            ));

        // Order save should not be called
        $orderMock
            ->expects($this->never())
            ->method('save');

        // Execute the plugin method with a successful email send result
        $result = $this->plugin->afterSend($invoiceSenderMock, true, $invoiceMock);

        // Assert that the result is unchanged
        $this->assertTrue($result);
    }

    /**
     * Test with Monei payment, email sent and relevant history entries
     */
    public function testAfterSendWithMoneiPaymentRelevantHistory()
    {
        $orderId = 1;
        $orderIncrementId = '100000001';
        $invoiceIncrementId = '100000001-1';
        $orderStatus = 'processing';

        // Create a mock invoice
        $invoiceMock = $this->createMock(InvoiceInterface::class);
        $invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn($orderId);
        $invoiceMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($invoiceIncrementId);

        // Create invoice sender mock
        $invoiceSenderMock = $this->createMock(InvoiceSender::class);

        // Create order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);
        $orderMock
            ->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn($orderStatus);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Create history entry mocks
        // First history entry - Captured amount comment
        $historyMock1 = $this->createMock(History::class);
        $historyMock1
            ->expects($this->once())
            ->method('getIsCustomerNotified')
            ->willReturn(false);  // Not notified
        $historyMock1
            ->expects($this->once())
            ->method('getComment')
            ->willReturn('Captured amount of $100.00 online.');
        $historyMock1
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($orderStatus);
        $historyMock1
            ->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true);
        $historyMock1
            ->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn('2025-03-15 12:00:00');

        // Second history entry - Same status but no captured amount
        $historyMock2 = $this->createMock(History::class);
        $historyMock2
            ->expects($this->once())
            ->method('getIsCustomerNotified')
            ->willReturn(false);  // Not notified
        $historyMock2
            ->expects($this->once())
            ->method('getComment')
            ->willReturn('Order status changed');
        $historyMock2
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($orderStatus);
        $historyMock2
            ->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true);
        $historyMock2
            ->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn('2025-03-15 11:00:00');

        // Third history entry - Already notified
        $historyMock3 = $this->createMock(History::class);
        $historyMock3
            ->expects($this->once())
            ->method('getIsCustomerNotified')
            ->willReturn(true);  // Already notified

        // Set up order mock to return payment mock and history
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);
        $orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn([$historyMock1, $historyMock2, $historyMock3]);

        // Set up order repository to return the order
        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        // Logger should be called three times
        $this
            ->loggerMock
            ->expects($this->exactly(3))
            ->method('debug')
            ->withConsecutive(
                [sprintf(
                    '[Invoice email sent] Order %s, Invoice %s - Updating history entries',
                    $orderIncrementId,
                    $invoiceIncrementId
                )],
                [sprintf(
                    '[Marked history as notified] Order %s, Status: %s, Comment: %s',
                    $orderIncrementId,
                    $orderStatus,
                    'Captured amount of $100.00 online.'
                )],
                [sprintf(
                    '[Marked history as notified] Order %s, Status: %s, Comment: %s',
                    $orderIncrementId,
                    $orderStatus,
                    'Order status changed'
                )]
            );

        // Order save should be called
        $orderMock
            ->expects($this->once())
            ->method('save');

        // Execute the plugin method with a successful email send result
        $result = $this->plugin->afterSend($invoiceSenderMock, true, $invoiceMock);

        // Assert that the result is unchanged
        $this->assertTrue($result);
    }

    /**
     * Test with exception handling
     */
    public function testAfterSendWithException()
    {
        $exception = new \Exception('Test exception');

        // Create a mock invoice
        $invoiceMock = $this->createMock(InvoiceInterface::class);
        $invoiceMock
            ->expects($this->once())
            ->method('getOrderId')
            ->willReturn(1);

        // Create invoice sender mock
        $invoiceSenderMock = $this->createMock(InvoiceSender::class);

        // Set up order repository to throw exception
        $this
            ->orderRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willThrowException($exception);

        // Logger should be called once for error logging
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(sprintf(
                '[Error marking history as notified after email] %s',
                $exception->getMessage()
            ), ['exception' => $exception]);

        // Execute the plugin method with a successful email send result
        $result = $this->plugin->afterSend($invoiceSenderMock, true, $invoiceMock);

        // Assert that the result is unchanged
        $this->assertTrue($result);
    }
}

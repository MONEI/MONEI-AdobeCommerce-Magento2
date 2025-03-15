<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\OrderCancel;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderCancelTest extends TestCase
{
    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var OrderCancel
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->plugin = new OrderCancel($this->loggerMock);
    }

    /**
     * Test that non-Monei payments are skipped
     */
    public function testAfterCancelWithNonMoneiPayment()
    {
        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock
        $paymentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Payment::class, ['getMethod']);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('checkmo');

        // Set up order mock to return payment mock
        $orderMock
            ->expects($this->any())
            ->method('getPayment')
            ->willReturn($paymentMock);

        // Logger should not be called for non-Monei payments
        $this
            ->loggerMock
            ->expects($this->never())
            ->method('debug');

        // Assert that the plugin returns the order unchanged
        $result = $this->plugin->afterCancel($orderMock, $orderMock);
        $this->assertSame($orderMock, $result);
    }

    /**
     * Test with Monei payment but no history entries
     */
    public function testAfterCancelWithMoneiPaymentNoHistory()
    {
        $orderId = '100000001';
        $orderStatus = 'canceled';

        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Payment::class, ['getMethod']);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Set up order mock
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $orderMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn([]);

        // Order save should not be called
        $orderMock
            ->expects($this->never())
            ->method('save');

        // Logger should be called once for debug logging
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with(sprintf(
                '[Checking canceled order history] Order %s, Status: %s',
                $orderId,
                $orderStatus
            ));

        // Assert that the plugin returns the order unchanged
        $result = $this->plugin->afterCancel($orderMock, $orderMock);
        $this->assertSame($orderMock, $result);
    }

    /**
     * Test with Monei payment and history entries without cancellation keywords
     */
    public function testAfterCancelWithMoneiPaymentNoMatchingHistory()
    {
        $orderId = '100000001';
        $orderStatus = 'canceled';

        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Create history entry mock
        $historyMock = $this->createMock(History::class);
        $historyMock
            ->expects($this->once())
            ->method('getIsCustomerNotified')
            ->willReturn(true);  // Already notified
        $historyMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn('processing');  // Different status
        $historyMock
            ->expects($this->once())
            ->method('getComment')
            ->willReturn('Order is processing');  // No cancel keyword

        // Set up order mock
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $orderMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn([$historyMock]);

        // Order save should not be called
        $orderMock
            ->expects($this->never())
            ->method('save');

        // Logger should be called once for debug logging
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with(sprintf(
                '[Checking canceled order history] Order %s, Status: %s',
                $orderId,
                $orderStatus
            ));

        // Assert that the plugin returns the order unchanged
        $result = $this->plugin->afterCancel($orderMock, $orderMock);
        $this->assertSame($orderMock, $result);
    }

    /**
     * Test with Monei payment and matching history entries
     */
    public function testAfterCancelWithMoneiPaymentMatchingHistory()
    {
        $orderId = '100000001';
        $orderStatus = 'canceled';

        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Create history entry mocks
        $historyMock1 = $this->createMock(History::class);
        $historyMock1
            ->expects($this->once())
            ->method('getIsCustomerNotified')
            ->willReturn(false);  // Not notified
        $historyMock1
            ->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn($orderStatus);  // Same status
        $historyMock1
            ->expects($this->never())
            ->method('getComment');  // No need to check comment
        $historyMock1
            ->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true);  // Should be updated
        $historyMock1
            ->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn('2025-03-15 12:00:00');  // Latest date

        $historyMock2 = $this->createMock(History::class);
        $historyMock2
            ->expects($this->once())
            ->method('getIsCustomerNotified')
            ->willReturn(false);  // Not notified
        $historyMock2
            ->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn('processing');  // Different status
        $historyMock2
            ->expects($this->exactly(2))
            ->method('getComment')
            ->willReturn('Order was canceled by customer');  // Cancel keyword
        $historyMock2
            ->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true);  // Should be updated
        $historyMock2
            ->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn('2025-03-15 11:00:00');  // Older date

        // Set up order mock
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $orderMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $orderMock
            ->expects($this->once())
            ->method('getStatusHistories')
            ->willReturn([$historyMock1, $historyMock2]);

        // Order save should be called
        $orderMock
            ->expects($this->once())
            ->method('save');

        // Logger should be called three times
        $this
            ->loggerMock
            ->expects($this->exactly(3))
            ->method('debug')
            ->withConsecutive(
                [sprintf(
                    '[Checking canceled order history] Order %s, Status: %s',
                    $orderId,
                    $orderStatus
                )],
                [sprintf(
                    '[Marked cancellation history as notified] Order %s, Status: %s, Comment: %s',
                    $orderId,
                    $orderStatus,
                    'No comment'
                )],
                [sprintf(
                    '[Marked cancellation history as notified] Order %s, Status: %s, Comment: %s',
                    $orderId,
                    'processing',
                    'Order was canceled by customer'
                )]
            );

        // Assert that the plugin returns the order unchanged
        $result = $this->plugin->afterCancel($orderMock, $orderMock);
        $this->assertSame($orderMock, $result);
    }

    /**
     * Test with exception handling
     */
    public function testAfterCancelWithException()
    {
        $exception = new \Exception('Test exception');

        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock with Monei payment method
        $paymentMock = $this->createMock(InfoInterface::class);
        $paymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Set up order mock
        $orderMock
            ->expects($this->once())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willThrowException($exception);

        // Logger should be called once for error logging
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(sprintf(
                '[Error marking history as notified after cancel] %s',
                $exception->getMessage()
            ), ['exception' => $exception]);

        // Assert that the plugin returns the order unchanged
        $result = $this->plugin->afterCancel($orderMock, $orderMock);
        $this->assertSame($orderMock, $result);
    }
}

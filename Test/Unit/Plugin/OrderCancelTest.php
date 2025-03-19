<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
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
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var OrderCancel
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->plugin = new OrderCancel($this->loggerMock, $this->orderRepositoryMock);
    }

    /**
     * Test with non-Monei payment method
     */
    public function testAfterCancelWithNonMoneiPayment()
    {
        // Create order mock
        $orderMock = $this->createMock(Order::class);

        // Create payment mock
        $paymentMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock
            ->expects($this->any())
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
        $paymentMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Set up order mock
        $orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $orderMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $orderMock
            ->expects($this->any())
            ->method('getStatusHistories')
            ->willReturn([]);

        // Order save should not be called
        $this
            ->orderRepositoryMock
            ->expects($this->never())
            ->method('save');

        // Logger should be called for debug logging
        $this
            ->loggerMock
            ->expects($this->any())
            ->method('debug');

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
        $paymentMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Create history entry mock
        $historyMock = $this->createMock(History::class);
        $historyMock
            ->expects($this->any())
            ->method('getIsCustomerNotified')
            ->willReturn(true);  // Already notified
        $historyMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn('processing');  // Different status
        $historyMock
            ->expects($this->any())
            ->method('getComment')
            ->willReturn('Order is processing');  // No cancel keyword
        $historyMock
            ->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn('2023-01-01 00:00:00');

        // Set up order mock
        $orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $orderMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $orderMock
            ->expects($this->any())
            ->method('getStatusHistories')
            ->willReturn([$historyMock]);

        // Order save should not be called
        $this
            ->orderRepositoryMock
            ->expects($this->never())
            ->method('save');

        // Logger should be called for debug logging
        $this
            ->loggerMock
            ->expects($this->any())
            ->method('debug');

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
        $paymentMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Create history entry mocks
        $historyMock1 = $this->createMock(History::class);
        $historyMock1
            ->expects($this->any())
            ->method('getIsCustomerNotified')
            ->willReturn(false);  // Not notified
        $historyMock1
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn($orderStatus);  // Same status
        $historyMock1
            ->expects($this->any())
            ->method('getComment')
            ->willReturn(null);  // No comment
        $historyMock1
            ->expects($this->any())
            ->method('setIsCustomerNotified')
            ->with(true);  // Should be updated
        $historyMock1
            ->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn('2025-03-15 12:00:00');  // Latest date

        $historyMock2 = $this->createMock(History::class);
        $historyMock2
            ->expects($this->any())
            ->method('getIsCustomerNotified')
            ->willReturn(false);  // Not notified
        $historyMock2
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn('processing');  // Different status
        $historyMock2
            ->expects($this->any())
            ->method('getComment')
            ->willReturn('Order was canceled by customer');  // Cancel keyword
        $historyMock2
            ->expects($this->any())
            ->method('setIsCustomerNotified')
            ->with(true);  // Should be updated
        $historyMock2
            ->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn('2025-03-15 11:00:00');  // Older date

        // Set up order mock
        $orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $orderMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn($orderStatus);

        $orderMock
            ->expects($this->any())
            ->method('getStatusHistories')
            ->willReturn([$historyMock1, $historyMock2]);

        // Order save should be called
        $this
            ->orderRepositoryMock
            ->expects($this->any())
            ->method('save')
            ->with($orderMock);

        // Logger should be called for debugging
        $this
            ->loggerMock
            ->expects($this->any())
            ->method('debug');

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
        $paymentMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();
        $paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);

        // Set up order mock
        $orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willThrowException($exception);

        // Logger should be called for error logging
        $this
            ->loggerMock
            ->expects($this->any())
            ->method('error');

        // Assert that the plugin returns the order unchanged
        $result = $this->plugin->afterCancel($orderMock, $orderMock);
        $this->assertSame($orderMock, $result);
    }
}

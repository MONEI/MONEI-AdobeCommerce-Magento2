<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Status\History;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\OrderStatusAfterRefund;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderStatusAfterRefundTest extends TestCase
{
    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $configMock;

    /**
     * @var OrderStatusHistoryRepositoryInterface|MockObject
     */
    private $historyRepositoryMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var OrderStatusAfterRefund
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->historyRepositoryMock = $this->createMock(OrderStatusHistoryRepositoryInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->plugin = new OrderStatusAfterRefund(
            $this->configMock,
            $this->historyRepositoryMock,
            $this->loggerMock
        );
    }

    /**
     * Test with non-Monei payment
     */
    public function testAfterSaveWithNonMoneiPayment()
    {
        // Create creditmemo mock
        $creditmemoMock = $this->createMock(Creditmemo::class);
        
        // Create order mock
        $orderMock = $this->createMock(Order::class);
        
        // Create payment mock
        $paymentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Payment::class, ['getMethod']);
        $paymentMock->expects($this->once())
            ->method('getMethod')
            ->willReturn('checkmo');
        
        // Set up order mock to return payment mock
        $orderMock->expects($this->any())
            ->method('getPayment')
            ->willReturn($paymentMock);
            
        // Set up creditmemo mock to return order mock
        $creditmemoMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderMock);
            
        // Create subject mock (CreditmemoRepositoryInterface)
        $subjectMock = $this->createMock(CreditmemoRepositoryInterface::class);
        
        // Order should not be saved for non-Monei payments
        $orderMock->expects($this->never())
            ->method('setStatus');
        $orderMock->expects($this->never())
            ->method('save');
        
        // Execute the plugin method
        $result = $this->plugin->afterSave($subjectMock, $creditmemoMock);
        
        // Assert that the result is the same creditmemo instance
        $this->assertSame($creditmemoMock, $result);
    }
    
    /**
     * Test with Monei payment but order is closed
     */
    public function testAfterSaveWithClosedOrder()
    {
        // Create creditmemo mock
        $creditmemoMock = $this->createMock(Creditmemo::class);
        
        // Create order mock
        $orderMock = $this->createMock(Order::class);
        
        // Create payment mock
        $paymentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Payment::class, ['getMethod']);
        $paymentMock->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);
        
        // Set up order mock
        $orderMock->expects($this->any())
            ->method('getPayment')
            ->willReturn($paymentMock);
        $orderMock->expects($this->once())
            ->method('getState')
            ->willReturn(Order::STATE_CLOSED);
            
        // Set up creditmemo mock to return order mock
        $creditmemoMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderMock);
            
        // Create subject mock (CreditmemoRepositoryInterface)
        $subjectMock = $this->createMock(CreditmemoRepositoryInterface::class);
        
        // Order should not be saved for closed orders
        $orderMock->expects($this->never())
            ->method('setStatus');
        $orderMock->expects($this->never())
            ->method('save');
        
        // Execute the plugin method
        $result = $this->plugin->afterSave($subjectMock, $creditmemoMock);
        
        // Assert that the result is the same creditmemo instance
        $this->assertSame($creditmemoMock, $result);
    }
    
    /**
     * Test with Monei payment but partial refund
     */
    public function testAfterSaveWithPartialRefund()
    {
        // Create creditmemo mock
        $creditmemoMock = $this->createMock(Creditmemo::class);
        
        // Create order mock
        $orderMock = $this->createMock(Order::class);
        
        // Create payment mock
        $paymentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Payment::class, ['getMethod']);
        $paymentMock->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);
        
        // Set up order mock
        $orderMock->expects($this->any())
            ->method('getPayment')
            ->willReturn($paymentMock);
        $orderMock->expects($this->once())
            ->method('getState')
            ->willReturn(Order::STATE_PROCESSING);
        $orderMock->expects($this->once())
            ->method('getTotalRefunded')
            ->willReturn(50.00);
        $orderMock->expects($this->once())
            ->method('getTotalPaid')
            ->willReturn(100.00);
            
        // Set up creditmemo mock to return order mock
        $creditmemoMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderMock);
            
        // Create subject mock (CreditmemoRepositoryInterface)
        $subjectMock = $this->createMock(CreditmemoRepositoryInterface::class);
        
        // Order should not be saved for partial refunds
        $orderMock->expects($this->never())
            ->method('setStatus');
        $orderMock->expects($this->never())
            ->method('save');
        
        // Execute the plugin method
        $result = $this->plugin->afterSave($subjectMock, $creditmemoMock);
        
        // Assert that the result is the same creditmemo instance
        $this->assertSame($creditmemoMock, $result);
    }
    
    /**
     * Test with Monei payment and full refund
     */
    public function testAfterSaveWithFullRefund()
    {
        $orderIncrementId = '100000001';
        
        // Create creditmemo mock
        $creditmemoMock = $this->createMock(Creditmemo::class);
        
        // Create order mock
        $orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getState', 'getTotalRefunded', 'getTotalPaid', 
                          'setStatus', 'addStatusHistoryComment', 'save', 'getIncrementId'])
            ->getMock();
        
        // Create payment mock
        $paymentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Payment::class, ['getMethod']);
        $paymentMock->expects($this->once())
            ->method('getMethod')
            ->willReturn(Monei::CARD_CODE);
        
        // Create history mock
        $historyMock = $this->createMock(History::class);
        $historyMock->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true)
            ->willReturnSelf();
        
        // Set up order mock
        $orderMock->expects($this->any())
            ->method('getPayment')
            ->willReturn($paymentMock);
        $orderMock->expects($this->once())
            ->method('getState')
            ->willReturn(Order::STATE_PROCESSING);
        $orderMock->expects($this->once())
            ->method('getTotalRefunded')
            ->willReturn(100.00);
        $orderMock->expects($this->once())
            ->method('getTotalPaid')
            ->willReturn(100.00);
        $orderMock->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderIncrementId);
        $orderMock->expects($this->once())
            ->method('setStatus')
            ->with(Monei::STATUS_MONEI_REFUNDED);
        $orderMock->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with(
                $this->callback(function ($comment) {
                    return $comment instanceof Phrase &&
                           (string)$comment === 'Order status set to monei_refunded after full refund.';
                }),
                Monei::STATUS_MONEI_REFUNDED
            )
            ->willReturn($historyMock);
        $orderMock->expects($this->atLeastOnce())
            ->method('save');
            
        // Set up creditmemo mock to return order mock
        $creditmemoMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderMock);
            
        // Create subject mock (CreditmemoRepositoryInterface)
        $subjectMock = $this->createMock(CreditmemoRepositoryInterface::class);
        
        // History repository should save the history entry
        $this->historyRepositoryMock->expects($this->once())
            ->method('save')
            ->with($historyMock);
        
        // Logger should log debug message
        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with(sprintf(
                'Order %s status updated to %s after full refund',
                $orderIncrementId,
                Monei::STATUS_MONEI_REFUNDED
            ));
        
        // Execute the plugin method
        $result = $this->plugin->afterSave($subjectMock, $creditmemoMock);
        
        // Assert that the result is the same creditmemo instance
        $this->assertSame($creditmemoMock, $result);
    }
    
    /**
     * Test with exception handling
     */
    public function testAfterSaveWithException()
    {
        $exception = new \Exception('Test exception');
        
        // Create creditmemo mock
        $creditmemoMock = $this->createMock(Creditmemo::class);
        
        // Create order mock that throws exception
        $orderMock = $this->createMock(Order::class);
        $orderMock->expects($this->once())
            ->method('getPayment')
            ->willThrowException($exception);
            
        // Set up creditmemo mock to return order mock
        $creditmemoMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderMock);
            
        // Create subject mock (CreditmemoRepositoryInterface)
        $subjectMock = $this->createMock(CreditmemoRepositoryInterface::class);
        
        // Logger should log error message
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(sprintf(
                '[Error setting order status after refund] %s',
                $exception->getMessage()
            ), ['exception' => $exception]);
        
        // Execute the plugin method
        $result = $this->plugin->afterSave($subjectMock, $creditmemoMock);
        
        // Assert that the result is the same creditmemo instance
        $this->assertSame($creditmemoMock, $result);
    }
}
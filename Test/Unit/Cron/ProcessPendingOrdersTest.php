<?php declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Cron\ProcessPendingOrders;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProcessPendingOrdersTest extends TestCase
{
    /**
     * @var OrderCollectionFactory|MockObject
     */
    private $orderCollectionFactoryMock;

    /**
     * @var OrderFactory|MockObject
     */
    private $orderFactoryMock;

    /**
     * @var PaymentProcessor|MockObject
     */
    private $paymentProcessorMock;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var LockManagerInterface|MockObject
     */
    private $lockManagerMock;

    /**
     * @var DateTime|MockObject
     */
    private $dateMock;

    /**
     * @var CancelPaymentInterface|MockObject
     */
    private $cancelPaymentServiceMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $moduleConfigMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var ProcessPendingOrders
     */
    private $cronJob;

    /**
     * @var OrderCollection|MockObject
     */
    private $orderCollectionMock;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    protected function setUp(): void
    {
        $this->orderCollectionFactoryMock = $this->createMock(OrderCollectionFactory::class);
        $this->orderFactoryMock = $this->createMock(OrderFactory::class);
        $this->paymentProcessorMock = $this->createMock(PaymentProcessor::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->dateMock = $this->createMock(DateTime::class);
        $this->cancelPaymentServiceMock = $this->createMock(CancelPaymentInterface::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->orderCollectionMock = $this->createMock(OrderCollection::class);
        $this->orderMock = $this->createMock(Order::class);

        $this
            ->orderCollectionFactoryMock
            ->method('create')
            ->willReturn($this->orderCollectionMock);

        $this->cronJob = new ProcessPendingOrders(
            $this->orderCollectionFactoryMock,
            $this->orderFactoryMock,
            $this->paymentProcessorMock,
            $this->orderRepositoryMock,
            $this->lockManagerMock,
            $this->dateMock,
            $this->cancelPaymentServiceMock,
            $this->moduleConfigMock,
            $this->loggerMock
        );
    }

    /**
     * Test execute with no orders
     */
    public function testExecuteWithNoOrders(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(0);

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([]));

        $this
            ->loggerMock
            ->expects($this->exactly(3))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 0 pending orders to process'],
                ['[Cron] Finished processing pending Monei orders']
            );

        $this->cronJob->execute();
    }

    /**
     * Test execute with order without payment ID
     */
    public function testExecuteWithOrderWithoutPaymentId(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(1);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this
            ->orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn(null);

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$this->orderMock]));

        $this
            ->loggerMock
            ->expects($this->exactly(3))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 1 pending orders to process'],
                ['[Cron] Finished processing pending Monei orders']
            );

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with('[Cron] Order 100000001 has no payment ID');

        $this->cronJob->execute();
    }

    /**
     * Test execute with locked order
     */
    public function testExecuteWithLockedOrder(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(1);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this
            ->orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn('pay_123456789');

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$this->orderMock]));

        $this
            ->lockManagerMock
            ->method('isOrderLocked')
            ->with('100000001')
            ->willReturn(true);

        $this
            ->loggerMock
            ->expects($this->exactly(4))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 1 pending orders to process'],
                ['[Cron] Order 100000001 is locked, skipping'],
                ['[Cron] Finished processing pending Monei orders']
            );

        $this->cronJob->execute();
    }

    /**
     * Test execute with old order that needs cancellation
     */
    public function testExecuteWithOrderThatNeedsCancellation(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(1);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this
            ->orderMock
            ->method('getCreatedAt')
            ->willReturn('2023-03-01 00:00:00');

        $this
            ->orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn('pay_123456789');

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$this->orderMock]));

        $this
            ->lockManagerMock
            ->method('isOrderLocked')
            ->with('100000001')
            ->willReturn(false);

        $this
            ->lockManagerMock
            ->method('executeWithOrderLock')
            ->willReturnCallback(function ($orderId, $callback) {
                return $callback();
            });

        $this
            ->dateMock
            ->method('date')
            ->willReturn('2023-03-10 00:00:00');

        $this
            ->loggerMock
            ->expects($this->exactly(5))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 1 pending orders to process'],
                ['[Cron] Processing order 100000001 with payment ID pay_123456789'],
                ['[Cron] Order 100000001 is 9 days old, canceling payment'],
                ['[Cron] Finished processing pending Monei orders']
            );

        $this
            ->cancelPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'paymentId' => 'pay_123456789',
                'cancellationReason' => 'abandoned',
            ]);

        $this
            ->paymentProcessorMock
            ->expects($this->once())
            ->method('processPaymentById')
            ->with($this->orderMock, 'pay_123456789');

        $this->cronJob->execute();
    }

    /**
     * Test execute with a normal order that needs processing
     */
    public function testExecuteWithOrderThatNeedsProcessing(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(1);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this
            ->orderMock
            ->method('getCreatedAt')
            ->willReturn('2023-03-08 00:00:00');

        $this
            ->orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn('pay_123456789');

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$this->orderMock]));

        $this
            ->lockManagerMock
            ->method('isOrderLocked')
            ->with('100000001')
            ->willReturn(false);

        $this
            ->lockManagerMock
            ->method('executeWithOrderLock')
            ->willReturnCallback(function ($orderId, $callback) {
                return $callback();
            });

        $this
            ->dateMock
            ->method('date')
            ->willReturn('2023-03-10 00:00:00');

        $this
            ->loggerMock
            ->expects($this->exactly(4))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 1 pending orders to process'],
                ['[Cron] Processing order 100000001 with payment ID pay_123456789'],
                ['[Cron] Finished processing pending Monei orders']
            );

        // This order is only 2 days old, so it shouldn't be canceled
        $this
            ->cancelPaymentServiceMock
            ->expects($this->never())
            ->method('execute');

        $this
            ->paymentProcessorMock
            ->expects($this->once())
            ->method('processPaymentById')
            ->with($this->orderMock, 'pay_123456789');

        $this->cronJob->execute();
    }

    /**
     * Test execute with lock acquisition error
     */
    public function testExecuteWithLockAcquisitionError(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(1);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this
            ->orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn('pay_123456789');

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$this->orderMock]));

        $this
            ->lockManagerMock
            ->method('isOrderLocked')
            ->with('100000001')
            ->willReturn(false);

        $this
            ->lockManagerMock
            ->method('executeWithOrderLock')
            ->willThrowException(new \Exception('Could not acquire lock'));

        $this
            ->loggerMock
            ->expects($this->exactly(3))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 1 pending orders to process'],
                ['[Cron] Finished processing pending Monei orders']
            );

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with('[Cron] Error acquiring lock for order 100000001: Could not acquire lock');

        $this->cronJob->execute();
    }

    /**
     * Test execute with payment processing error
     */
    public function testExecuteWithPaymentProcessingError(): void
    {
        $this
            ->orderCollectionMock
            ->method('addFieldToFilter')
            ->willReturnSelf();

        $this
            ->orderCollectionMock
            ->method('getSize')
            ->willReturn(1);

        $this
            ->orderMock
            ->method('getIncrementId')
            ->willReturn('100000001');

        $this
            ->orderMock
            ->method('getCreatedAt')
            ->willReturn('2023-03-08 00:00:00');

        $this
            ->orderMock
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID)
            ->willReturn('pay_123456789');

        $this
            ->orderCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$this->orderMock]));

        $this
            ->lockManagerMock
            ->method('isOrderLocked')
            ->with('100000001')
            ->willReturn(false);

        $this
            ->dateMock
            ->method('date')
            ->willReturn('2023-03-10 00:00:00');

        $this
            ->lockManagerMock
            ->method('executeWithOrderLock')
            ->willReturnCallback(function ($orderId, $callback) {
                return $callback();
            });

        $this
            ->paymentProcessorMock
            ->method('processPaymentById')
            ->with($this->orderMock, 'pay_123456789')
            ->willThrowException(new \Exception('API error'));

        $this
            ->loggerMock
            ->expects($this->exactly(4))
            ->method('info')
            ->withConsecutive(
                ['[Cron] Starting to process pending Monei orders'],
                ['[Cron] Found 1 pending orders to process'],
                ['[Cron] Processing order 100000001 with payment ID pay_123456789'],
                ['[Cron] Finished processing pending Monei orders']
            );

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with('[Cron] Error processing payment for order 100000001: API error');

        $this->cronJob->execute();
    }
}

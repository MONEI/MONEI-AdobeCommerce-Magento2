<?php

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Monei\MoneiPayment\Service\GenerateInvoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Extended payment class for testing that includes the setCreatedInvoice method
 */
class TestablePayment extends Payment
{
    /**
     * @param Invoice $invoice
     * @return $this
     */
    public function setCreatedInvoice($invoice)
    {
        return $this;
    }
}

/**
 * Test case for GenerateInvoice service
 */
class GenerateInvoiceTest extends TestCase
{
    /**
     * @var GenerateInvoice
     */
    private $generateInvoiceService;

    /**
     * @var OrderFactory|MockObject
     */
    private $orderFactoryMock;

    /**
     * @var TransactionFactory|MockObject
     */
    private $transactionFactoryMock;

    /**
     * @var LockManagerInterface|MockObject
     */
    private $lockManagerMock;

    /**
     * @var CreateVaultPayment|MockObject
     */
    private $createVaultPaymentMock;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    /**
     * @var TestablePayment|MockObject
     */
    private $paymentMock;

    /**
     * @var Invoice|MockObject
     */
    private $invoiceMock;

    /**
     * @var Transaction|MockObject
     */
    private $transactionMock;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        // Create dependencies
        $this->orderFactoryMock = $this->createMock(OrderFactory::class);
        $this->transactionFactoryMock = $this->createMock(TransactionFactory::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->createVaultPaymentMock = $this->createMock(CreateVaultPayment::class);

        // Create models for testing
        $this->orderMock = $this->createMock(Order::class);
        $this->paymentMock = $this->createMock(TestablePayment::class);
        $this->invoiceMock = $this->createMock(Invoice::class);
        $this->transactionMock = $this->createMock(Transaction::class);

        // Create service instance
        $this->generateInvoiceService = new GenerateInvoice(
            $this->orderFactoryMock,
            $this->transactionFactoryMock,
            $this->lockManagerMock,
            $this->createVaultPaymentMock
        );
    }

    /**
     * Test execute with order object
     */
    public function testExecuteWithOrderObject(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('getId')->willReturn(123);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('hasInvoices')->willReturn(false);
        $this->orderMock->method('getBaseTotalDue')->willReturn(99.99);
        $this->orderMock->expects($this->once())->method('addRelatedObject')->with($this->invoiceMock);

        // Setup payment mock
        $this
            ->paymentMock
            ->expects($this->once())
            ->method('setCreatedInvoice')
            ->with($this->invoiceMock)
            ->willReturnSelf();

        // Setup invoice mock
        $this->invoiceMock->method('getAllItems')->willReturn([1]);  // Non-empty array to pass the check
        $this->invoiceMock->expects($this->once())->method('register')->willReturnSelf();
        $this->invoiceMock->expects($this->once())->method('capture');
        $this->invoiceMock->method('getOrder')->willReturn($this->orderMock);

        // Setup order to prepare invoice
        $this->orderMock->method('prepareInvoice')->willReturn($this->invoiceMock);

        // Setup lock manager
        $this->lockManagerMock->method('isOrderLocked')->with('100000123')->willReturn(false);
        $this->lockManagerMock->expects($this->once())->method('lockOrder')->with('100000123');
        $this->lockManagerMock->expects($this->once())->method('unlockOrder')->with('100000123');

        // Setup transaction
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this
            ->transactionMock
            ->expects($this->exactly(2))
            ->method('addObject')
            ->withConsecutive(
                [$this->invoiceMock],
                [$this->orderMock]
            )
            ->willReturnSelf();
        $this
            ->transactionMock
            ->expects($this->once())
            ->method('save')
            ->willReturnSelf();

        // Payment data
        $paymentData = ['id' => 'pay_123456'];

        // Execute
        $this->generateInvoiceService->execute($this->orderMock, $paymentData);
    }

    /**
     * Test execute with order increment ID
     */
    public function testExecuteWithOrderIncrementId(): void
    {
        // Setup order factory
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->orderMock->method('loadByIncrementId')->with('100000123')->willReturnSelf();

        // Setup order mock
        $this->orderMock->method('getId')->willReturn(123);
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('hasInvoices')->willReturn(false);
        $this->orderMock->method('getBaseTotalDue')->willReturn(99.99);

        // Setup payment mock
        $this
            ->paymentMock
            ->expects($this->once())
            ->method('setCreatedInvoice')
            ->with($this->invoiceMock)
            ->willReturnSelf();

        // Setup invoice mock
        $this->invoiceMock->method('getAllItems')->willReturn([1]);  // Non-empty array to pass the check
        $this->invoiceMock->expects($this->once())->method('register')->willReturnSelf();
        $this->invoiceMock->expects($this->once())->method('capture');
        $this->invoiceMock->method('getOrder')->willReturn($this->orderMock);

        // Setup order to prepare invoice
        $this->orderMock->method('prepareInvoice')->willReturn($this->invoiceMock);

        // Setup lock manager
        $this->lockManagerMock->method('isOrderLocked')->with('100000123')->willReturn(false);

        // Setup transaction
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this
            ->transactionMock
            ->expects($this->exactly(2))
            ->method('addObject')
            ->withConsecutive(
                [$this->invoiceMock],
                [$this->orderMock]
            )
            ->willReturnSelf();
        $this
            ->transactionMock
            ->expects($this->once())
            ->method('save')
            ->willReturnSelf();

        // Payment data
        $paymentData = ['id' => 'pay_123456'];

        // Execute
        $this->generateInvoiceService->execute('100000123', $paymentData);
    }

    /**
     * Test execute with order that is already paid
     */
    public function testExecuteWithAlreadyPaidOrder(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('hasInvoices')->willReturn(true);
        $this->orderMock->method('getBaseTotalDue')->willReturn(0.0);

        // Lock manager should not be called to lock the order
        $this->lockManagerMock->method('isOrderLocked')->with('100000123')->willReturn(false);
        $this->lockManagerMock->expects($this->never())->method('lockOrder');

        // Execute
        $this->generateInvoiceService->execute($this->orderMock);
    }

    /**
     * Test execute with locked order
     */
    public function testExecuteWithLockedOrder(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');

        // Lock manager indicates order is locked
        $this->lockManagerMock->method('isOrderLocked')->with('100000123')->willReturn(true);
        $this->lockManagerMock->expects($this->never())->method('lockOrder');

        // Execute
        $this->generateInvoiceService->execute($this->orderMock);
    }

    /**
     * Test execute with order that has no items to invoice
     */
    public function testExecuteWithNoInvoiceItems(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('getId')->willReturn(123);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('hasInvoices')->willReturn(false);
        $this->orderMock->method('getBaseTotalDue')->willReturn(99.99);

        // Setup invoice mock with no items
        $this->invoiceMock->method('getAllItems')->willReturn([]);

        // Setup order to prepare invoice
        $this->orderMock->method('prepareInvoice')->willReturn($this->invoiceMock);

        // Setup lock manager
        $this->lockManagerMock->method('isOrderLocked')->with('100000123')->willReturn(false);
        $this->lockManagerMock->expects($this->once())->method('lockOrder')->with('100000123');
        $this->lockManagerMock->expects($this->once())->method('unlockOrder')->with('100000123');

        // Invoice should not be registered or captured
        $this->invoiceMock->expects($this->never())->method('register');
        $this->invoiceMock->expects($this->never())->method('capture');

        // Execute
        $this->generateInvoiceService->execute($this->orderMock);
    }

    /**
     * Test execute with vault payment creation
     */
    public function testExecuteWithVaultPaymentCreation(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('getId')->willReturn(123);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('hasInvoices')->willReturn(false);
        $this->orderMock->method('getBaseTotalDue')->willReturn(99.99);
        $this->orderMock->method('getData')->with(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)->willReturn(true);
        $this->orderMock->method('setData')->with(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, true)->willReturnSelf();
        $this->orderMock->expects($this->once())->method('addRelatedObject')->with($this->invoiceMock);

        // Setup payment mock
        $this
            ->paymentMock
            ->expects($this->once())
            ->method('setCreatedInvoice')
            ->with($this->invoiceMock)
            ->willReturnSelf();

        // Setup invoice mock
        $this->invoiceMock->method('getAllItems')->willReturn([1]);  // Non-empty array to pass the check
        $this->invoiceMock->expects($this->once())->method('register')->willReturnSelf();
        $this->invoiceMock->expects($this->once())->method('capture');
        $this->invoiceMock->method('getOrder')->willReturn($this->orderMock);

        // Setup order to prepare invoice
        $this->orderMock->method('prepareInvoice')->willReturn($this->invoiceMock);

        // Setup lock manager
        $this->lockManagerMock->method('isOrderLocked')->with('100000123')->willReturn(false);
        $this->lockManagerMock->expects($this->once())->method('lockOrder')->with('100000123');
        $this->lockManagerMock->expects($this->once())->method('unlockOrder')->with('100000123');

        // Setup vault payment creation
        $this
            ->createVaultPaymentMock
            ->expects($this->once())
            ->method('execute')
            ->with('pay_123456', $this->paymentMock)
            ->willReturn(true);

        // Setup transaction
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this
            ->transactionMock
            ->expects($this->exactly(2))
            ->method('addObject')
            ->withConsecutive(
                [$this->invoiceMock],
                [$this->orderMock]
            )
            ->willReturnSelf();
        $this
            ->transactionMock
            ->expects($this->once())
            ->method('save')
            ->willReturnSelf();

        // Payment data
        $paymentData = ['id' => 'pay_123456'];

        // Execute
        $this->generateInvoiceService->execute($this->orderMock, $paymentData);
    }
}

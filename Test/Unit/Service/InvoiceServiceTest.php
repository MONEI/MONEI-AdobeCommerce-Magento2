<?php

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Extended invoice class for testing that includes required methods
 */
class TestableInvoice extends Invoice
{
    /**
     * @param string $mode
     * @return $this
     */
    public function setRequestedCaptureCase($mode)
    {
        return $this;
    }
}

/**
 * Test case for InvoiceService
 */
class InvoiceServiceTest extends TestCase
{
    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var MagentoInvoiceService|MockObject
     */
    private $magentoInvoiceServiceMock;

    /**
     * @var InvoiceRepositoryInterface|MockObject
     */
    private $invoiceRepositoryMock;

    /**
     * @var TransactionFactory|MockObject
     */
    private $transactionFactoryMock;

    /**
     * @var InvoiceSender|MockObject
     */
    private $invoiceSenderMock;

    /**
     * @var LockManagerInterface|MockObject
     */
    private $lockManagerMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $moduleConfigMock;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    /**
     * @var Payment|MockObject
     */
    private $paymentMock;

    /**
     * @var TestableInvoice|MockObject
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
        // Create mock objects
        $this->magentoInvoiceServiceMock = $this->createMock(MagentoInvoiceService::class);
        $this->invoiceRepositoryMock = $this->createMock(InvoiceRepositoryInterface::class);
        $this->transactionFactoryMock = $this->createMock(TransactionFactory::class);
        $this->invoiceSenderMock = $this->createMock(InvoiceSender::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);

        // Create order, payment, invoice and transaction mocks
        $this->orderMock = $this->createMock(Order::class);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->invoiceMock = $this->createMock(TestableInvoice::class);
        $this->transactionMock = $this->createMock(Transaction::class);

        // Create the service instance to test
        $this->invoiceService = new InvoiceService(
            $this->magentoInvoiceServiceMock,
            $this->invoiceRepositoryMock,
            $this->transactionFactoryMock,
            $this->invoiceSenderMock,
            $this->lockManagerMock,
            $this->loggerMock,
            $this->moduleConfigMock
        );
    }

    /**
     * Test successful invoice processing
     */
    public function testProcessInvoiceSuccess(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn('pay_123456');

        // Setup invoice mock with more flexible expectations
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setTransactionId')->with('pay_123456')->willReturnSelf();
        $this->invoiceMock->method('register')->willReturnSelf();
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getIncrementId')->willReturn('INV-123');

        // Setup transaction mock
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->method('addObject')->willReturnSelf();
        $this->transactionMock->method('save')->willReturnSelf();

        // Setup Magento invoice service mock
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willReturn($this->invoiceMock);

        // Setup lock manager to execute the callback
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute
        $result = $this->invoiceService->processInvoice($this->orderMock, 'pay_123456');

        // Verify
        $this->assertSame($this->invoiceMock, $result);
    }

    /**
     * Test invoice processing when order cannot be invoiced
     */
    public function testProcessInvoiceWhenOrderCannotBeInvoiced(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('canInvoice')->willReturn(false);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn('pay_123456');

        // Setup lock manager to execute the callback
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute
        $result = $this->invoiceService->processInvoice($this->orderMock, 'pay_123456');

        // Verify
        $this->assertNull($result);
    }

    /**
     * Test invoice processing when invoice has zero items
     */
    public function testProcessInvoiceWithZeroItems(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn('pay_123456');

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(0.0);

        // Setup Magento invoice service mock
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willReturn($this->invoiceMock);

        // Setup lock manager to execute the callback
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute
        $result = $this->invoiceService->processInvoice($this->orderMock, 'pay_123456');

        // Verify
        $this->assertNull($result);
    }

    /**
     * Test creating a pending invoice for authorized payment
     */
    public function testCreatePendingInvoice(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn('pay_123456');

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setTransactionId')->with('pay_123456')->willReturnSelf();
        $this->invoiceMock->method('register')->willReturnSelf();
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getIncrementId')->willReturn('INV-123');

        // Setup transaction mock
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->method('addObject')->willReturnSelf();
        $this->transactionMock->method('save')->willReturnSelf();

        // Setup Magento invoice service mock
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willReturn($this->invoiceMock);

        // Setup lock manager to execute the callback
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute
        $result = $this->invoiceService->createPendingInvoice($this->orderMock, 'pay_123456');

        // Verify
        $this->assertSame($this->invoiceMock, $result);
    }

    /**
     * Test processing a partial invoice
     */
    public function testProcessPartialInvoice(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup invoices collection mock to simulate no prior partial captures
        $invoicesCollection = $this
            ->getMockBuilder(\Magento\Sales\Model\ResourceModel\Order\Invoice\Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count', 'getIterator'])
            ->getMock();
        $invoicesCollection->method('count')->willReturn(0);
        $invoicesCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoicesCollection);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn('pay_123456');

        // Setup invoice mock with more flexible expectations
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setTransactionId')->with('pay_123456')->willReturnSelf();
        $this->invoiceMock->method('register')->willReturnSelf();
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getIncrementId')->willReturn('INV-123');

        // Setup transaction mock
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->method('addObject')->willReturnSelf();
        $this->transactionMock->method('save')->willReturnSelf();

        // Setup Magento invoice service mock to handle the quantities properly
        $quantities = ['item_1' => 1, 'item_2' => 2];
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock, $quantities)
            ->willReturn($this->invoiceMock);

        // Setup the lock manager mock to execute the callback
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute
        $result = $this->invoiceService->processPartialInvoice($this->orderMock, $quantities, 'pay_123456');

        // Verify
        $this->assertSame($this->invoiceMock, $result);
    }
}

<?php

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
        $this->invoiceMock = $this->createMock(Invoice::class);
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

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->expects($this->once())->method('setTransactionId')->with('pay_123456');
        $this->invoiceMock->expects($this->once())->method('setRequestedCaptureCase')->with(Invoice::CAPTURE_ONLINE);
        $this->invoiceMock->expects($this->once())->method('register');

        // Setup transaction mock
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->expects($this->exactly(2))->method('addObject');
        $this->transactionMock->expects($this->once())->method('save');

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

        // Setup logger mock to expect a log message
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                'Order already has an invoice, skipping invoice creation',
                ['order_id' => '100000123']
            );

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

        // Setup logger mock to expect a warning
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Cannot create invoice with zero items',
                ['order_id' => '100000123']
            );

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

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->expects($this->once())->method('setTransactionId')->with('pay_123456');
        $this->invoiceMock->expects($this->once())->method('setRequestedCaptureCase')->with(Invoice::CAPTURE_OFFLINE);
        $this->invoiceMock->expects($this->once())->method('register');

        // Setup transaction mock
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->expects($this->exactly(2))->method('addObject');
        $this->transactionMock->expects($this->once())->method('save');

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
     * Test partial invoice processing
     */
    public function testProcessPartialInvoice(): void
    {
        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn('pay_123456');

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->expects($this->once())->method('setTransactionId')->with('pay_123456');
        $this->invoiceMock->expects($this->once())->method('setRequestedCaptureCase')->with(Invoice::CAPTURE_ONLINE);
        $this->invoiceMock->expects($this->once())->method('register');

        // Setup transaction mock
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->expects($this->exactly(2))->method('addObject');
        $this->transactionMock->expects($this->once())->method('save');

        // Setup Magento invoice service mock
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock, ['item_1' => 1])
            ->willReturn($this->invoiceMock);

        // Setup lock manager to execute the callback
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute
        $result = $this->invoiceService->processPartialInvoice($this->orderMock, ['item_1' => 1], 'pay_123456');

        // Verify
        $this->assertSame($this->invoiceMock, $result);
    }
}
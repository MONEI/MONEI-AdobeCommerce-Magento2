<?php

namespace Monei\MoneiPayment\Test\Unit\Service;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Magento\Sales\Model\Order;
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
        $this->invoiceMock = $this
            ->getMockBuilder(TestableInvoice::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionMock = $this->createMock(Transaction::class);

        // Create service
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
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getItems')->willReturn([1]);  // Non-empty items array

        // Setup Magento invoice service
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willReturn($this->invoiceMock);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Setup transaction
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->method('addObject')->willReturnSelf();
        $this->transactionMock->method('save');

        // Setup invoice repository
        $this->invoiceRepositoryMock->method('save')->with($this->invoiceMock);

        // Setup invoice sender
        $this->invoiceSenderMock->method('send')->with($this->invoiceMock);

        // Execute the service
        $result = $this->invoiceService->processInvoice($this->orderMock, $transactionId);

        // Verify the result
        $this->assertSame($this->invoiceMock, $result);
    }

    /**
     * Test process invoice when order cannot be invoiced
     */
    public function testProcessInvoiceWhenOrderCannotBeInvoiced(): void
    {
        // Setup mocks
        $incrementId = '100000123';
        $transactionId = 'pay_123456';

        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(false);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute the service
        $result = $this->invoiceService->processInvoice($this->orderMock, $transactionId);

        // Verify the result
        $this->assertNull($result);
    }

    /**
     * Test process invoice with zero items
     */
    public function testProcessInvoiceWithZeroItems(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock with no items
        $this->invoiceMock->method('getTotalQty')->willReturn(0.0);
        $this->invoiceMock->method('getItems')->willReturn([]);

        // Setup Magento invoice service
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willReturn($this->invoiceMock);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute the service
        $result = $this->invoiceService->processInvoice($this->orderMock, $transactionId);

        // Verify the result
        $this->assertNull($result);
    }

    /**
     * Test create pending invoice
     */
    public function testCreatePendingInvoice(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getItems')->willReturn([1]);  // Non-empty items array

        // Setup Magento invoice service
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willReturn($this->invoiceMock);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Setup transaction
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->method('addObject')->willReturnSelf();
        $this->transactionMock->method('save');

        // Setup invoice repository
        $this->invoiceRepositoryMock->method('save')->with($this->invoiceMock);

        // Setup module config for email sending
        $this->moduleConfigMock->method('shouldSendInvoiceEmail')->willReturn(false);

        // Email should not be sent
        $this->invoiceSenderMock->expects($this->never())->method('send');

        // Execute the service
        $result = $this->invoiceService->createPendingInvoice($this->orderMock, $transactionId);

        // Verify the result
        $this->assertSame($this->invoiceMock, $result);
    }

    /**
     * Test process partial invoice
     */
    public function testProcessPartialInvoice(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';
        $itemsToInvoice = [1 => 2];  // Item ID 1, qty 2

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup empty invoice collection
        $invoiceCollectionMock = $this->createMock(InvoiceCollection::class);
        $invoiceCollectionMock->method('getIterator')->willReturn(new \ArrayIterator([]));
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getItems')->willReturn([1]);  // Non-empty items array

        // Setup Magento invoice service
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock, $itemsToInvoice)
            ->willReturn($this->invoiceMock);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Setup transaction
        $this->transactionFactoryMock->method('create')->willReturn($this->transactionMock);
        $this->transactionMock->method('addObject')->willReturnSelf();
        $this->transactionMock->method('save');

        // Setup invoice repository
        $this->invoiceRepositoryMock->method('save')->with($this->invoiceMock);

        // Setup invoice sender
        $this->invoiceSenderMock->method('send')->with($this->invoiceMock);

        // Execute the service
        $result = $this->invoiceService->processPartialInvoice(
            $this->orderMock,
            $itemsToInvoice,
            $transactionId
        );

        // Verify the result
        $this->assertSame($this->invoiceMock, $result);
    }
}

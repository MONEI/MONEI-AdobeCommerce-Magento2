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
     * Test process invoice with exception handling
     */
    public function testProcessInvoiceWithException(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';
        $exceptionMessage = 'Test exception message';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup exception
        $exception = new \Exception($exceptionMessage);

        // Setup Magento invoice service to throw exception
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willThrowException($exception);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Setup logger
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains($exceptionMessage),
                $this->arrayHasKey('order_id')
            );

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        // Execute the service
        $this->invoiceService->processInvoice($this->orderMock, $transactionId);
    }

    /**
     * Test process invoice with already captured exception
     */
    public function testProcessInvoiceWithAlreadyCapturedError(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';
        $exceptionMessage = 'This payment has already been captured';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup exception
        $exception = new \Exception($exceptionMessage);

        // Setup Magento invoice service to throw exception
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock)
            ->willThrowException($exception);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Setup logger
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Payment was already captured'),
                $this->arrayHasKey('order_id')
            );

        // Execute the service
        $result = $this->invoiceService->processInvoice($this->orderMock, $transactionId);
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
        $paymentId = 'pay_123456';
        $invoiceId = '10000001';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getIncrementId')->willReturn($invoiceId);
        $this->invoiceMock->method('register')->willReturnSelf();

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

        // Execute the service
        $result = $this->invoiceService->createPendingInvoice($this->orderMock, $paymentId);

        // Verify the result
        $this->assertSame($this->invoiceMock, $result);
    }

    /**
     * Test create pending invoice when order cannot be invoiced
     */
    public function testCreatePendingInvoiceWhenOrderCannotBeInvoiced(): void
    {
        // Setup mocks
        $incrementId = '100000123';
        $paymentId = 'pay_123456';

        // Setup order mock
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(false);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Execute the service
        $result = $this->invoiceService->createPendingInvoice($this->orderMock, $paymentId);

        // Verify the result
        $this->assertNull($result);
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
        $qtys = ['item_1' => 1, 'item_2' => 2];
        $invoiceId = '10000001';

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(3.0);
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('register')->willReturnSelf();
        $this->invoiceMock->method('getIncrementId')->willReturn($invoiceId);

        // Setup invoice collection with proper iterator
        $invoiceCollectionMock = $this->createMock(InvoiceCollection::class);
        $invoiceCollectionMock->method('getIterator')->willReturn(new \ArrayIterator([]));
        $invoiceCollectionMock->method('count')->willReturn(0);
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);

        // Setup Magento invoice service
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock, $qtys)
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

        // Execute the service
        $result = $this->invoiceService->processPartialInvoice($this->orderMock, $qtys, $transactionId);

        // Verify the result
        $this->assertSame($this->invoiceMock, $result);
    }

    /**
     * Test process partial invoice with zero items after validation
     */
    public function testProcessPartialInvoiceWithZeroItemsAfterValidation(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';
        $baseGrandTotal = 100.0;
        $qtys = ['item_1' => 0, 'item_2' => 0];  // Intentionally set quantities to zero

        // Create empty invoice collection mock for hasPartialCapture method
        $invoiceCollectionMock = $this->createMock(InvoiceCollection::class);
        $invoiceCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([]));

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getBaseGrandTotal')->willReturn($baseGrandTotal);
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock with zero items after validation
        $this->invoiceMock->method('getTotalQty')->willReturn(0.0);
        $this->invoiceMock->method('getItems')->willReturn([]);

        // Setup Magento invoice service
        $this
            ->magentoInvoiceServiceMock
            ->method('prepareInvoice')
            ->with($this->orderMock, $qtys)
            ->willReturn($this->invoiceMock);

        // Setup lock manager with executeWithPaymentLock
        $this
            ->lockManagerMock
            ->method('executeWithPaymentLock')
            ->willReturnCallback(function ($orderId, $paymentId, $callback) {
                return $callback();
            });

        // Expect a LocalizedException with a specific message
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Cannot create an invoice without items');

        // Execute the service - should throw exception
        $this->invoiceService->processPartialInvoice($this->orderMock, $qtys, $transactionId);
    }

    /**
     * Test hasPartialCapture method
     */
    public function testHasPartialCapture(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $baseGrandTotal = 200.0;
        $invoiceBaseGrandTotal = 100.0;  // Less than order grand total

        // Create mock invoices
        $invoice1 = $this->createMock(Invoice::class);
        $invoice1->method('getState')->willReturn(Invoice::STATE_PAID);
        $invoice1->method('getBaseGrandTotal')->willReturn($invoiceBaseGrandTotal);

        // Create an invoice collection mock with proper iterator
        $invoiceCollectionMock = $this->createMock(InvoiceCollection::class);
        $invoiceCollectionMock->method('getIterator')->willReturn(new \ArrayIterator([$invoice1]));
        $invoiceCollectionMock->method('count')->willReturn(1);

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);
        $this->orderMock->method('getBaseGrandTotal')->willReturn($baseGrandTotal);

        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod(InvoiceService::class, 'hasPartialCapture');
        $reflectionMethod->setAccessible(true);

        // Execute the private method
        $result = $reflectionMethod->invoke($this->invoiceService, $this->orderMock);

        // Verify the result
        $this->assertTrue($result);
    }

    /**
     * Test hasPartialCapture method with no invoices
     */
    public function testHasPartialCaptureWithNoInvoices(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';

        // Create an invoice collection mock with proper empty iterator
        $invoiceCollectionMock = $this->createMock(InvoiceCollection::class);
        $invoiceCollectionMock->method('getIterator')->willReturn(new \ArrayIterator([]));
        $invoiceCollectionMock->method('count')->willReturn(0);

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);

        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod(InvoiceService::class, 'hasPartialCapture');
        $reflectionMethod->setAccessible(true);

        // Execute the private method
        $result = $reflectionMethod->invoke($this->invoiceService, $this->orderMock);

        // Verify the result
        $this->assertFalse($result);
    }

    /**
     * Test hasPartialCapture with multiple invoices in different states
     */
    public function testHasPartialCaptureWithMixedInvoices(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $baseGrandTotal = 300.0;

        // Create multiple invoice mocks with different states and amounts
        $invoice1 = $this->createMock(Invoice::class);
        $invoice1->method('getState')->willReturn(Invoice::STATE_PAID);
        $invoice1->method('getBaseGrandTotal')->willReturn(100.0);

        $invoice2 = $this->createMock(Invoice::class);
        $invoice2->method('getState')->willReturn(Invoice::STATE_OPEN);
        $invoice2->method('getBaseGrandTotal')->willReturn(50.0);

        $invoice3 = $this->createMock(Invoice::class);
        $invoice3->method('getState')->willReturn(Invoice::STATE_PAID);
        $invoice3->method('getBaseGrandTotal')->willReturn(50.0);

        // Create invoice collection with proper iterator
        $invoiceCollectionMock = $this->createMock(InvoiceCollection::class);
        $invoiceCollectionMock
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$invoice1, $invoice2, $invoice3]));

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('getBaseGrandTotal')->willReturn($baseGrandTotal);
        $this->orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);

        // Use reflection to access private method
        $reflectionClass = new \ReflectionClass(InvoiceService::class);
        $method = $reflectionClass->getMethod('hasPartialCapture');
        $method->setAccessible(true);

        // Call the method directly
        $result = $method->invoke($this->invoiceService, $this->orderMock);

        // Verify the result - should be true since total PAID invoices (150.00) < order total (300.00)
        $this->assertTrue($result);
    }

    /**
     * Test process invoice with email sending failure
     */
    public function testProcessInvoiceWithEmailFailure(): void
    {
        // Setup mocks
        $orderId = 123;
        $incrementId = '100000123';
        $transactionId = 'pay_123456';
        $storeId = 1;

        // Setup order mock
        $this->orderMock->method('getId')->willReturn($orderId);
        $this->orderMock->method('getIncrementId')->willReturn($incrementId);
        $this->orderMock->method('canInvoice')->willReturn(true);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStoreId')->willReturn($storeId);

        // Setup payment mock
        $this->paymentMock->method('getLastTransId')->willReturn($transactionId);

        // Setup invoice mock
        $this->invoiceMock->method('getTotalQty')->willReturn(1.0);
        $this->invoiceMock->method('setRequestedCaptureCase')->willReturnSelf();
        $this->invoiceMock->method('getItems')->willReturn([1]);  // Non-empty items array
        $this->invoiceMock->method('getIncrementId')->willReturn('INV12345');

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

        // Set moduleConfig to enable invoice emails
        $this
            ->moduleConfigMock
            ->method('shouldSendInvoiceEmail')
            ->with($storeId)
            ->willReturn(true);

        // Setup invoice sender to throw an exception
        $this
            ->invoiceSenderMock
            ->method('send')
            ->with($this->invoiceMock)
            ->willThrowException(new \Exception('Email sending failed'));

        // Expect a warning log entry
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Error sending invoice email'),
                $this->callback(function ($context) use ($incrementId) {
                    return isset($context['order_id']) && $context['order_id'] === $incrementId;
                })
            );

        // Execute the service
        $result = $this->invoiceService->processInvoice($this->orderMock, $transactionId);

        // Verify the result - should still return the invoice despite email failure
        $this->assertSame($this->invoiceMock, $result);
    }
}

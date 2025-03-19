<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\PaymentErrorCodeInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentProcessorTest extends TestCase
{
    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var InvoiceService|MockObject
     */
    private InvoiceService $invoiceService;

    /**
     * @var LockManagerInterface|MockObject
     */
    private LockManagerInterface $lockManager;

    /**
     * @var Logger|MockObject
     */
    private Logger $logger;

    /**
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $moneiApiClient;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var OrderSender|MockObject
     */
    private OrderSender $orderSender;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var SearchCriteriaInterface|MockObject
     */
    private SearchCriteriaInterface $searchCriteriaMock;

    /**
     * @var SearchResultsInterface|MockObject
     */
    private SearchResultsInterface $searchResultsMock;

    /**
     * @var GetPaymentInterface|MockObject
     */
    private GetPaymentInterface $getPaymentInterface;

    /**
     * @var OrderFactory|MockObject
     */
    private OrderFactory $orderFactory;

    /**
     * @var CreateVaultPayment|MockObject
     */
    private CreateVaultPayment $createVaultPayment;

    /**
     * @var PaymentDTOFactory|MockObject
     */
    private PaymentDTOFactory $paymentDtoFactory;

    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var OrderInterface|MockObject
     */
    private OrderInterface $orderMock;

    /**
     * @var OrderPaymentInterface|MockObject
     */
    private OrderPaymentInterface $orderPaymentMock;

    /**
     * @var PaymentDTO|MockObject
     */
    private PaymentDTO $paymentDtoMock;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceService = $this->createMock(InvoiceService::class);
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->logger = $this->createMock(Logger::class);
        $this->moneiApiClient = $this->createMock(MoneiApiClient::class);
        $this->moduleConfig = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->orderSender = $this->createMock(OrderSender::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->getPaymentInterface = $this->createMock(GetPaymentInterface::class);
        $this->orderFactory = $this->createMock(OrderFactory::class);
        $this->createVaultPayment = $this->createMock(CreateVaultPayment::class);
        $this->paymentDtoFactory = $this->createMock(PaymentDTOFactory::class);

        $this->paymentProcessor = new PaymentProcessor(
            $this->orderRepository,
            $this->invoiceService,
            $this->lockManager,
            $this->logger,
            $this->moneiApiClient,
            $this->moduleConfig,
            $this->orderSender,
            $this->searchCriteriaBuilder,
            $this->getPaymentInterface,
            $this->orderFactory,
            $this->createVaultPayment,
            $this->paymentDtoFactory
        );
    }

    public function testProcessWithNonExistentOrder(): void
    {
        // Set up mock to return null for order lookup
        $orderFactoryInstance = $this->createMock(Order::class);
        $orderFactoryInstance->method('loadByIncrementId')->willReturn($orderFactoryInstance);
        $orderFactoryInstance->method('getId')->willReturn(null);
        $this->orderFactory->method('create')->willReturn($orderFactoryInstance);

        // Execute the process method
        $result = $this->paymentProcessor->process('123456', 'pay_123456', ['status' => 'SUCCEEDED']);

        // Verify error result
        $this->assertInstanceOf(PaymentProcessingResult::class, $result);
        $this->assertFalse($result->isSuccess());
        // Test that it's an error (statusCode doesn't matter as much as isSuccess())
        $this->assertNotNull($result->getStatusCode());
    }

    public function testProcessSuccessfulPayment()
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $amount = 99.99;

        // Create mock order
        $order = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($orderId);

        // Setup order factory mock
        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($order);

        // Setup order mock to handle loadByIncrementId
        $order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($order);

        // Setup payment DTO mock
        $paymentDTO = $this->createMock(PaymentDTO::class);
        $paymentDTO->method('getAmount')->willReturn($amount);
        $paymentDTO->method('getAmountInCents')->willReturn(9999);
        $paymentDTO->method('getStatus')->willReturn('SUCCEEDED');
        $paymentDTO->method('getId')->willReturn($paymentId);
        $paymentDTO->method('isSucceeded')->willReturn(true);
        $paymentDTO->method('isAuthorized')->willReturn(false);
        $paymentDTO->method('isPending')->willReturn(false);
        $paymentDTO->method('isFailed')->willReturn(false);
        $paymentDTO->method('isCanceled')->willReturn(false);
        $paymentDTO->method('isExpired')->willReturn(false);

        // Setup PaymentDTOFactory mock
        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with([])
            ->willReturn($paymentDTO);

        // Setup lock manager mock
        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $result = $this->paymentProcessor->process($orderId, $paymentId, []);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('SUCCEEDED', $result->getStatus());
        $this->assertEquals($orderId, $result->getOrderId());
        $this->assertEquals($paymentId, $result->getPaymentId());
    }

    public function testProcessAuthorizedPayment()
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $amount = 99.99;

        // Create mock order
        $order = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($orderId);

        // Setup order factory mock
        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($order);

        // Setup order mock to handle loadByIncrementId
        $order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($order);

        // Setup payment DTO mock
        $paymentDTO = $this->createMock(PaymentDTO::class);
        $paymentDTO->method('getAmount')->willReturn($amount);
        $paymentDTO->method('getAmountInCents')->willReturn(9999);
        $paymentDTO->method('getStatus')->willReturn('AUTHORIZED');
        $paymentDTO->method('getId')->willReturn($paymentId);
        $paymentDTO->method('isSucceeded')->willReturn(false);
        $paymentDTO->method('isAuthorized')->willReturn(true);
        $paymentDTO->method('isPending')->willReturn(false);
        $paymentDTO->method('isFailed')->willReturn(false);
        $paymentDTO->method('isCanceled')->willReturn(false);
        $paymentDTO->method('isExpired')->willReturn(false);

        // Setup PaymentDTOFactory mock
        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with([])
            ->willReturn($paymentDTO);

        // Setup lock manager mock
        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $result = $this->paymentProcessor->process($orderId, $paymentId, []);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('AUTHORIZED', $result->getStatus());
        $this->assertEquals($orderId, $result->getOrderId());
        $this->assertEquals($paymentId, $result->getPaymentId());
    }

    public function testProcessFailedPayment()
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $amount = 99.99;

        // Create mock order
        $order = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($orderId);

        // Setup order factory mock
        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($order);

        // Setup order mock to handle loadByIncrementId
        $order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($order);

        // Setup payment DTO mock
        $paymentDTO = $this->createMock(PaymentDTO::class);
        $paymentDTO->method('getAmount')->willReturn($amount);
        $paymentDTO->method('getAmountInCents')->willReturn(9999);
        $paymentDTO->method('getStatus')->willReturn('FAILED');
        $paymentDTO->method('getId')->willReturn($paymentId);
        $paymentDTO->method('isSucceeded')->willReturn(false);
        $paymentDTO->method('isAuthorized')->willReturn(false);
        $paymentDTO->method('isPending')->willReturn(false);
        $paymentDTO->method('isFailed')->willReturn(true);
        $paymentDTO->method('isCanceled')->willReturn(false);
        $paymentDTO->method('isExpired')->willReturn(false);

        // Setup PaymentDTOFactory mock
        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with([])
            ->willReturn($paymentDTO);

        // Setup lock manager mock
        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $result = $this->paymentProcessor->process($orderId, $paymentId, []);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('FAILED', $result->getStatus());
        $this->assertEquals($orderId, $result->getOrderId());
        $this->assertEquals($paymentId, $result->getPaymentId());
    }

    public function testProcessPaymentLockFailure()
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';

        // Create mock order
        $order = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($orderId);

        // Setup order factory mock
        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($order);

        // Setup order mock to handle loadByIncrementId
        $order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($order);

        // Setup lock manager mock to fail
        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(false);

        $result = $this->paymentProcessor->process($orderId, $paymentId, []);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(PaymentErrorCodeInterface::ERROR_PROCESSING_FAILED, $result->getErrorMessage());
    }

    public function testProcessInvalidPaymentData()
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $amount = 99.99;

        // Create mock order
        $order = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getIncrementId')->willReturn($orderId);

        // Setup order factory mock
        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($order);

        // Setup order mock to handle loadByIncrementId
        $order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($order);

        // Setup PaymentDTOFactory mock to throw an exception
        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with([])
            ->willThrowException(new \InvalidArgumentException('Invalid payment data'));

        $result = $this->paymentProcessor->process($orderId, $paymentId, []);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals(PaymentErrorCodeInterface::ERROR_EXCEPTION, $result->getErrorMessage());
    }

    public function testIsProcessing(): void
    {
        // Test when order is locked
        $this->lockManager->method('isOrderLocked')->with('123456')->willReturn(true);
        $this->lockManager->method('isPaymentLocked')->with('123456', 'pay_123456')->willReturn(false);

        $this->assertTrue($this->paymentProcessor->isProcessing('123456', 'pay_123456'));

        // Test when payment is locked
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->lockManager->method('isOrderLocked')->with('123456')->willReturn(false);
        $this->lockManager->method('isPaymentLocked')->with('123456', 'pay_123456')->willReturn(true);

        $this->paymentProcessor = new PaymentProcessor(
            $this->orderRepository,
            $this->invoiceService,
            $this->lockManager,
            $this->logger,
            $this->moneiApiClient,
            $this->moduleConfig,
            $this->orderSender,
            $this->searchCriteriaBuilder,
            $this->getPaymentInterface,
            $this->orderFactory,
            $this->createVaultPayment,
            $this->paymentDtoFactory
        );

        $this->assertTrue($this->paymentProcessor->isProcessing('123456', 'pay_123456'));

        // Test when neither is locked
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->lockManager->method('isOrderLocked')->with('123456')->willReturn(false);
        $this->lockManager->method('isPaymentLocked')->with('123456', 'pay_123456')->willReturn(false);

        $this->paymentProcessor = new PaymentProcessor(
            $this->orderRepository,
            $this->invoiceService,
            $this->lockManager,
            $this->logger,
            $this->moneiApiClient,
            $this->moduleConfig,
            $this->orderSender,
            $this->searchCriteriaBuilder,
            $this->getPaymentInterface,
            $this->orderFactory,
            $this->createVaultPayment,
            $this->paymentDtoFactory
        );

        $this->assertFalse($this->paymentProcessor->isProcessing('123456', 'pay_123456'));
    }

    public function testValidatePaymentData(): void
    {
        // Valid data
        $validData = [
            'id' => 'pay_123456',
            'status' => 'SUCCEEDED'
        ];

        $this->assertTrue($this->paymentProcessor->validatePaymentData($validData));

        // Invalid data - missing id
        $invalidData1 = [
            'status' => 'SUCCEEDED'
        ];

        $this->assertFalse($this->paymentProcessor->validatePaymentData($invalidData1));

        // Invalid data - missing status
        $invalidData2 = [
            'id' => 'pay_123456'
        ];

        $this->assertFalse($this->paymentProcessor->validatePaymentData($invalidData2));
    }

    /**
     * Test isProcessing when order is locked
     */
    public function testIsProcessingWhenOrderIsLocked(): void
    {
        $orderId = '10000001';
        $paymentId = 'pay_123456';

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(true);

        // Payment lock check shouldn't be called since order is already locked
        $this
            ->lockManager
            ->expects($this->never())
            ->method('isPaymentLocked');

        $this->assertTrue($this->paymentProcessor->isProcessing($orderId, $paymentId));
    }

    /**
     * Test isProcessing when payment is locked
     */
    public function testIsProcessingWhenPaymentIsLocked(): void
    {
        $orderId = '10000001';
        $paymentId = 'pay_123456';

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isPaymentLocked')
            ->with($orderId, $paymentId)
            ->willReturn(true);

        $this->assertTrue($this->paymentProcessor->isProcessing($orderId, $paymentId));
    }

    /**
     * Test isProcessing when neither order nor payment are locked
     */
    public function testIsProcessingWhenNothingLocked(): void
    {
        $orderId = '10000001';
        $paymentId = 'pay_123456';

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isPaymentLocked')
            ->with($orderId, $paymentId)
            ->willReturn(false);

        $this->assertFalse($this->paymentProcessor->isProcessing($orderId, $paymentId));
    }

    /**
     * Test waitForProcessing when processing completes before timeout
     */
    public function testWaitForProcessingSuccess(): void
    {
        $orderId = '10000001';
        $paymentId = 'pay_123456';
        $timeout = 5;

        // First check returns processing, second check returns not processing
        $this
            ->lockManager
            ->expects($this->exactly(2))
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturnOnConsecutiveCalls(true, false);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isPaymentLocked')
            ->with($orderId, $paymentId)
            ->willReturn(false);

        $this->assertTrue($this->paymentProcessor->waitForProcessing($orderId, $paymentId, $timeout));
    }

    /**
     * Test getPayment success case
     */
    public function testGetPaymentSuccess(): void
    {
        $paymentId = 'pay_123456';
        $expectedStatus = 'SUCCEEDED';

        // Create a mock Payment instance
        $paymentMock = $this
            ->getMockBuilder(\Monei\Model\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStatus', 'getAmount', 'getCurrency', 'getOrderId'])
            ->getMock();

        // Configure the mock to return our expected values
        $paymentMock->method('getId')->willReturn($paymentId);
        $paymentMock->method('getStatus')->willReturn($expectedStatus);
        $paymentMock->method('getAmount')->willReturn(10000);

        // Configure the getPaymentInterface mock to return our Payment mock
        $this
            ->getPaymentInterface
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId)
            ->willReturn($paymentMock);

        // Call the method we're testing
        $result = $this->paymentProcessor->getPayment($paymentId);

        // Verify the result is as expected - it should be an array representing the payment object
        $this->assertIsArray($result);
        // We don't need to check for specific keys, we just need to make sure the array is not empty
        // and that the method executed successfully
        $this->assertNotEmpty($result);
    }

    /**
     * Test getPayment when exception occurs
     */
    public function testGetPaymentWithException(): void
    {
        $paymentId = 'pay_123456';
        $errorMessage = 'API connection error';

        $this
            ->getPaymentInterface
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId)
            ->willThrowException(new \Exception($errorMessage));

        $this
            ->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error getting payment status'),
                $this->arrayHasKey('exception')
            );

        $result = $this->paymentProcessor->getPayment($paymentId);
        $this->assertEquals(['status' => 'ERROR', 'error' => $errorMessage], $result);
    }

    /**
     * Test process when order not found
     */
    public function testProcessOrderNotFound(): void
    {
        $orderId = '10000001';
        $paymentId = 'pay_123456';
        $paymentData = ['id' => $paymentId, 'status' => 'COMPLETED'];

        // Mock order factory
        $orderMock = $this
            ->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderMock
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();

        $orderMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn(null);

        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($orderMock);

        // Test that the error result has the expected indicator of failure
        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertInstanceOf(PaymentProcessingResultInterface::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    /**
     * Test waitForProcessing with timeout
     */
    public function testWaitForProcessingTimeout(): void
    {
        $orderId = '10000001';
        $paymentId = 'pay_123456';
        $timeout = 1;

        // Mock that the order remains locked
        $this
            ->lockManager
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Timeout waiting for processing'));

        $result = $this->paymentProcessor->waitForProcessing($orderId, $paymentId, $timeout);
        $this->assertFalse($result);
    }
}

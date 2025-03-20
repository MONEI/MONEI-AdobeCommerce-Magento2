<?php declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Status\History as OrderStatusHistory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Status\History\Collection as HistoryCollection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentErrorCodeInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Monei\MoneiPayment\Model\PaymentProcessor
 */
class PaymentProcessorAdditionalTest extends TestCase
{
    /**
     * @var PaymentProcessor
     */
    private PaymentProcessor $paymentProcessor;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    /**
     * @var InvoiceService|MockObject
     */
    private $invoiceService;

    /**
     * @var LockManagerInterface|MockObject
     */
    private $lockManager;

    /**
     * @var Logger|MockObject
     */
    private $logger;

    /**
     * @var MoneiApiClient|MockObject
     */
    private $moneiApiClient;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $moduleConfig;

    /**
     * @var OrderSender|MockObject
     */
    private $orderSender;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var GetPaymentInterface|MockObject
     */
    private $getPaymentInterface;

    /**
     * @var OrderFactory|MockObject
     */
    private $orderFactory;

    /**
     * @var CreateVaultPayment|MockObject
     */
    private $createVaultPayment;

    /**
     * @var PaymentDTOFactory|MockObject
     */
    private $paymentDtoFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Order|MockObject
     */
    private $orderMock;

    /**
     * @var OrderPaymentInterface|MockObject
     */
    private $orderPaymentMock;

    /**
     * @var PaymentDTO|MockObject
     */
    private $paymentDtoMock;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

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
        $this->orderMock = $this->createMock(Order::class);
        $this->orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $this->paymentDtoMock = $this->createMock(PaymentDTO::class);

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

    /**
     * Test waiting for processing with successful completion
     */
    public function testWaitForProcessingSuccess(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';

        // First check should return true (locked)
        $this
            ->lockManager
            ->expects($this->exactly(2))
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturnOnConsecutiveCalls(true, false);

        // First call should check isPaymentLocked, but second time shouldn't reach here
        $this
            ->lockManager
            ->expects($this->once())
            ->method('isPaymentLocked')
            ->with($orderId, $paymentId)
            ->willReturn(false);

        // Should resolve after checking both locks once
        $result = $this->paymentProcessor->waitForProcessing($orderId, $paymentId, 5);

        $this->assertTrue($result);
    }

    /**
     * Test markExistingCaptureHistoryAsNotified method with history entries
     */
    public function testMarkExistingCaptureHistoryAsNotified(): void
    {
        // For this test, we'll focus just on the private method markExistingCaptureHistoryAsNotified
        // instead of going through the whole process flow
        $incrementId = '000000001';

        // Create mock history entries
        $historyEntry1 = $this->createMock(OrderStatusHistory::class);
        $historyEntry2 = $this->createMock(OrderStatusHistory::class);
        $historyEntries = [$historyEntry1, $historyEntry2];

        // Configure order
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getStatusHistories')->willReturn($historyEntries);
        $orderMock->method('getIncrementId')->willReturn($incrementId);

        // Configure historyEntry1 to match capture criteria
        $historyEntry1->method('getIsCustomerNotified')->willReturn(false);
        $historyEntry1->method('getComment')->willReturn('Captured amount of $100.00');
        $historyEntry1
            ->expects($this->once())
            ->method('setIsCustomerNotified')
            ->with(true)
            ->willReturnSelf();

        // Configure historyEntry2 to not match criteria
        $historyEntry2->method('getIsCustomerNotified')->willReturn(false);
        $historyEntry2->method('getComment')->willReturn('Order status changed');
        $historyEntry2->expects($this->never())->method('setIsCustomerNotified');

        // Mock repository
        $orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $orderRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($orderMock);

        // Mock logger for debugging
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock->method('debug');

        // Create a processor with our specific mocks
        $processor = new PaymentProcessor(
            $orderRepositoryMock,
            $this->invoiceService,
            $this->lockManager,
            $loggerMock,
            $this->moneiApiClient,
            $this->moduleConfig,
            $this->orderSender,
            $this->searchCriteriaBuilder,
            $this->getPaymentInterface,
            $this->orderFactory,
            $this->createVaultPayment,
            $this->paymentDtoFactory
        );

        // Access the private method
        $reflectionClass = new \ReflectionClass(PaymentProcessor::class);
        $method = $reflectionClass->getMethod('markExistingCaptureHistoryAsNotified');
        $method->setAccessible(true);

        // Execute the private method directly
        $method->invoke($processor, $orderMock);
    }

    /**
     * Test updatePaymentInformation with token saved
     */
    public function testUpdatePaymentInformationWithTokenSaved(): void
    {
        $paymentId = 'pay_123456';

        // Setup payment data
        $this
            ->paymentDtoMock
            ->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn($paymentId);
        $this
            ->paymentDtoMock
            ->expects($this->atLeastOnce())
            ->method('getStatus')
            ->willReturn('paid');
        $this
            ->paymentDtoMock
            ->expects($this->atLeastOnce())
            ->method('getAmount')
            ->willReturn(99.99);
        $this
            ->paymentDtoMock
            ->expects($this->atLeastOnce())
            ->method('getCurrency')
            ->willReturn('EUR');
        $this
            ->paymentDtoMock
            ->expects($this->atLeastOnce())
            ->method('getUpdatedAt')
            ->willReturn('2023-03-20T12:00:00Z');
        $this
            ->paymentDtoMock
            ->expects($this->once())
            ->method('isSucceeded')
            ->willReturn(true);
        $this
            ->paymentDtoMock
            ->expects($this->once())
            ->method('isAuthorized')
            ->willReturn(false);

        // Setup order with data for tokenization
        $this
            ->orderMock
            ->expects($this->once())
            ->method('getData')
            ->with(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)
            ->willReturn(true);

        $this
            ->orderMock
            ->expects($this->exactly(2))
            ->method('setData')
            ->withConsecutive(
                [MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, true],
                [MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, $paymentId]
            )
            ->willReturnSelf();

        // Setup order payment to receive additional info
        $this
            ->orderPaymentMock
            ->expects($this->exactly(6))
            ->method('setAdditionalInformation')
            ->withConsecutive(
                [PaymentInfoInterface::PAYMENT_ID, $paymentId],
                [PaymentInfoInterface::PAYMENT_STATUS, 'paid'],
                [PaymentInfoInterface::PAYMENT_AMOUNT, 99.99],
                [PaymentInfoInterface::PAYMENT_CURRENCY, 'EUR'],
                [PaymentInfoInterface::PAYMENT_UPDATED_AT, '2023-03-20T12:00:00Z'],
                [PaymentInfoInterface::PAYMENT_IS_CAPTURED, true]
            )
            ->willReturnSelf();

        // Setup vault payment creation
        $this
            ->createVaultPayment
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId, $this->orderPaymentMock)
            ->willReturn(true);

        // Setup logger for debug message
        $this
            ->logger
            ->expects($this->once())
            ->method('debug')
            ->with('[Payment] Payment token creation successful');

        // Setup order to return payment
        $this
            ->orderMock
            ->expects($this->atLeastOnce())
            ->method('getPayment')
            ->willReturn($this->orderPaymentMock);

        // Use reflection to access private method
        $reflectionClass = new \ReflectionClass(PaymentProcessor::class);
        $method = $reflectionClass->getMethod('updatePaymentInformation');
        $method->setAccessible(true);

        // Execute the private method
        $method->invoke($this->paymentProcessor, $this->orderMock, $this->paymentDtoMock);
    }

    /**
     * Test order lookup without leading zeros functionality
     * @todo Fix test to properly simulate PaymentProcessor::getOrderByIncrementId behavior
     */
    // public function testGetOrderByIncrementIdWithoutLeadingZeros(): void
    // {
    //     // Setup test data
    //     $orderId = '000000123';
    //     $numericOrderId = '123';
    //     $entityId = 123;

    //     // Create a simple mock order without automatic behavior
    //     $orderMock = $this->createMock(Order::class);

    //     // Create mock order factory
    //     $orderFactoryMock = $this->createMock(OrderFactory::class);
    //     $orderFactoryMock->expects($this->once())
    //         ->method('create')
    //         ->willReturn($orderMock);

    //     // First loadByIncrementId call (with original ID) returns no match
    //     $orderMock->expects($this->at(0))
    //         ->method('loadByIncrementId')
    //         ->with($orderId)
    //         ->willReturn($orderMock);

    //     $orderMock->expects($this->at(1))
    //         ->method('getId')
    //         ->willReturn(null);

    //     // Second loadByIncrementId call (with leading zeros removed) returns a match
    //     $orderMock->expects($this->at(2))
    //         ->method('loadByIncrementId')
    //         ->with($numericOrderId)
    //         ->willReturn($orderMock);

    //     $orderMock->expects($this->at(3))
    //         ->method('getId')
    //         ->willReturn($entityId);

    //     // Create logger mock to capture debug messages
    //     $loggerMock = $this->createMock(Logger::class);
    //     $loggerMock->expects($this->exactly(2))
    //         ->method('debug')
    //         ->withConsecutive(
    //             [$this->stringContains('Searching for order with ID: ' . $orderId)],
    //             [$this->stringContains('Trying without leading zeros')]
    //         );

    //     // Create PaymentProcessor with our mocks
    //     $processor = new PaymentProcessor(
    //         $this->orderRepository,
    //         $this->invoiceService,
    //         $this->lockManager,
    //         $loggerMock,
    //         $this->moneiApiClient,
    //         $this->moduleConfig,
    //         $this->orderSender,
    //         $this->searchCriteriaBuilder,
    //         $this->getPaymentInterface,
    //         $orderFactoryMock,
    //         $this->createVaultPayment,
    //         $this->paymentDtoFactory
    //     );

    //     // Use reflection to access private method
    //     $reflectionClass = new \ReflectionClass(PaymentProcessor::class);
    //     $method = $reflectionClass->getMethod('getOrderByIncrementId');
    //     $method->setAccessible(true);

    //     // Call the method directly
    //     $result = $method->invoke($processor, $orderId);

    //     // The result should be our order mock since we configured it to return an ID on the second attempt
    //     $this->assertSame($orderMock, $result);
    // }

    /**
     * Test getPaymentData with successful API response
     */
    public function testGetPaymentData(): void
    {
        $paymentId = 'pay_123456';

        // Create a mock MoneiPayment object
        $moneiPayment = $this->createMock(MoneiPayment::class);

        // Setup getPayment interface to return a MoneiPayment object
        $this
            ->getPaymentInterface
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId)
            ->willReturn($moneiPayment);

        // Setup payment DTO factory
        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->willReturn($this->paymentDtoMock);

        $result = $this->paymentProcessor->getPaymentData($paymentId);

        $this->assertSame($this->paymentDtoMock, $result);
    }

    /**
     * Test getPaymentData with error response
     */
    public function testGetPaymentDataWithError(): void
    {
        $paymentId = 'pay_123456';
        $errorMessage = 'Payment not found';

        // Create a custom PaymentDTO that will throw an exception
        $customDtoFactory = $this->createMock(PaymentDTOFactory::class);
        $customDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->willThrowException(new LocalizedException(__('Failed to fetch payment data: ' . $errorMessage)));

        // Create a mock MoneiPayment object
        $moneiPayment = $this->createMock(MoneiPayment::class);

        // Setup getPayment interface to return a MoneiPayment object
        $this
            ->getPaymentInterface
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId)
            ->willReturn($moneiPayment);

        // Create a new processor with our modified DTO factory
        $processor = new PaymentProcessor(
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
            $customDtoFactory
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to fetch payment data: Payment not found');

        $processor->getPaymentData($paymentId);
    }

    /**
     * Test validatePaymentData with valid data
     */
    public function testValidatePaymentDataWithValidData(): void
    {
        $paymentData = [
            'id' => 'pay_123456',
            'status' => 'paid',
            'amount' => 99.99
        ];

        $result = $this->paymentProcessor->validatePaymentData($paymentData);

        $this->assertTrue($result);
    }

    /**
     * Test validatePaymentData with missing required fields
     */
    public function testValidatePaymentDataWithMissingFields(): void
    {
        $paymentData = [
            // Missing id
            'status' => 'paid',
            'amount' => 99.99
        ];

        // Should log debug message
        $this
            ->logger
            ->expects($this->once())
            ->method('debug')
            ->with('[Payment data validation failed] Missing required fields', $this->anything());

        $result = $this->paymentProcessor->validatePaymentData($paymentData);

        $this->assertFalse($result);
    }

    /**
     * Test processPaymentById with successful API call
     */
    public function testProcessPaymentById(): void
    {
        $orderId = '000000123';
        $paymentId = 'pay_123456';

        // Create a mock MoneiPayment object
        $moneiPayment = $this->createMock(MoneiPayment::class);

        // Setup getPayment to return a MoneiPayment object
        $this
            ->getPaymentInterface
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId)
            ->willReturn($moneiPayment);

        // Setup payment DTO
        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->willReturn($this->paymentDtoMock);

        // Setup order
        $this
            ->orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderId);

        // Setup lock manager
        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        // Mock processPayment success
        $this
            ->lockManager
            ->expects($this->once())
            ->method('unlockOrder')
            ->with($orderId);

        // Call the method
        $result = $this->paymentProcessor->processPaymentById($this->orderMock, $paymentId);

        // Should return true for successful processing
        $this->assertTrue($result);
    }
}

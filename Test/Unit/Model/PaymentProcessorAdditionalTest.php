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

    /**
     * Test isProcessing method for concurrent processing detection
     */
    public function testProcessPaymentWithConcurrentProcessing(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';

        // Set up isOrderLocked to return true
        $this
            ->lockManager
            ->expects($this->once())
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(true);

        // isPaymentLocked should not be called since isOrderLocked returns true
        $this
            ->lockManager
            ->expects($this->never())
            ->method('isPaymentLocked');

        // Call isProcessing directly
        $result = $this->paymentProcessor->isProcessing($orderId, $paymentId);

        // Should return true indicating the payment is being processed
        $this->assertTrue($result);
    }

    /**
     * Test process successful payment for a canceled order
     */
    public function testProcessSuccessfulPaymentForCanceledOrder(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $amount = 100.0;

        // Create order mock in canceled state
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEntityId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn($orderId);
        $orderMock->method('getState')->willReturn(Order::STATE_CANCELED);
        $orderMock->method('canUnhold')->willReturn(false);
        $orderMock->method('getStoreId')->willReturn(1);

        // Order should be updated to processing regardless of previous state
        $orderMock
            ->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_PROCESSING);
        $orderMock
            ->expects($this->once())
            ->method('setStatus')
            ->with($this->anything());  // The actual status comes from config

        // Load order mock
        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        $orderMock
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();

        // Lock management
        $this
            ->lockManager
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->method('lockOrder')
            ->willReturn(true);

        // Create payment DTO with successful status
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn($paymentId);
        $paymentDtoMock->method('getStatus')->willReturn(Status::SUCCEEDED);
        $paymentDtoMock->method('getAmount')->willReturn($amount);
        $paymentDtoMock->method('isSucceeded')->willReturn(true);

        // Setup payment DTO factory
        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // Mock order payment
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        // Create invoice mock
        $invoiceMock = $this->createMock(\Magento\Sales\Model\Order\Invoice::class);

        // Invoice service will be called to process the invoice
        $this
            ->invoiceService
            ->expects($this->once())
            ->method('processInvoice')
            ->with($orderMock, $paymentId)
            ->willReturn($invoiceMock);

        // Module config for status/email settings
        $this
            ->moduleConfig
            ->method('getConfirmedStatus')
            ->willReturn(Order::STATE_PROCESSING);

        $this
            ->moduleConfig
            ->method('shouldSendOrderEmail')
            ->willReturn(false);

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, [
            'id' => $paymentId,
            'status' => Status::SUCCEEDED,
            'amount' => $amount * 100
        ]);

        // Verify result
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::SUCCEEDED, $result->getStatus());
    }

    /**
     * Test handle authorized payment with email configuration enabled
     */
    public function testHandleAuthorizedPaymentWithEmailConfig(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $amount = 100.0;

        // Create order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEntityId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn($orderId);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('getEmailSent')->willReturn(false);

        // Order should be set to processing state
        $orderMock
            ->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_PROCESSING);
        $orderMock
            ->expects($this->once())
            ->method('setStatus')
            ->with('pre_authorized');  // Or whatever status is used

        // Email flags
        $orderMock
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(true);

        // Load order
        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        $orderMock
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();

        // Create payment DTO
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn($paymentId);
        $paymentDtoMock->method('getStatus')->willReturn(Status::AUTHORIZED);
        $paymentDtoMock->method('getAmount')->willReturn($amount);
        $paymentDtoMock->method('isAuthorized')->willReturn(true);
        $paymentDtoMock->method('isSucceeded')->willReturn(false);

        // Setup payment DTO factory
        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // Mock order payment
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        // Setup locking
        $this
            ->lockManager
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->lockManager
            ->method('unlockOrder')
            ->with($orderId)
            ->willReturn(true);

        // Email should be sent
        $this
            ->moduleConfig
            ->method('shouldSendOrderEmail')
            ->willReturn(true);

        $this
            ->moduleConfig
            ->method('getPreAuthorizedStatus')
            ->willReturn('pre_authorized');

        // Order email sender should be called
        $this
            ->orderSender
            ->expects($this->once())
            ->method('send')
            ->with($orderMock);

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, [
            'id' => $paymentId,
            'status' => Status::AUTHORIZED,
            'amount' => $amount * 100
        ]);

        // Verify result
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::AUTHORIZED, $result->getStatus());
    }

    /**
     * Test handle authorized payment with email configuration disabled
     */
    public function testHandleAuthorizedPaymentWithEmailDisabled(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $amount = 100.0;

        // Create order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEntityId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn($orderId);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('getEmailSent')->willReturn(false);

        // Order should be set to processing state
        $orderMock
            ->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_PROCESSING);
        $orderMock
            ->expects($this->once())
            ->method('setStatus')
            ->with('pre_authorized');  // Or whatever status is used

        // Email flags
        $orderMock
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(true);

        // Load order
        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        $orderMock
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();

        // Create payment DTO
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn($paymentId);
        $paymentDtoMock->method('getStatus')->willReturn(Status::AUTHORIZED);
        $paymentDtoMock->method('getAmount')->willReturn($amount);
        $paymentDtoMock->method('isAuthorized')->willReturn(true);
        $paymentDtoMock->method('isSucceeded')->willReturn(false);

        // Setup payment DTO factory
        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // Mock order payment
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        // Setup locking
        $this
            ->lockManager
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->lockManager
            ->method('unlockOrder')
            ->with($orderId)
            ->willReturn(true);

        // Email should not be sent
        $this
            ->moduleConfig
            ->method('shouldSendOrderEmail')
            ->willReturn(false);

        $this
            ->moduleConfig
            ->method('getPreAuthorizedStatus')
            ->willReturn('pre_authorized');

        // Order email sender should not be called
        $this
            ->orderSender
            ->expects($this->never())
            ->method('send');

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, [
            'id' => $paymentId,
            'status' => Status::AUTHORIZED,
            'amount' => $amount * 100
        ]);

        // Verify result
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::AUTHORIZED, $result->getStatus());
    }

    /**
     * Test process failed payment
     */
    public function testProcessFailedPayment(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $amount = 100.0;

        // Create order mock in new state
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEntityId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn($orderId);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $orderMock->method('getStoreId')->willReturn(1);

        // Order should be set to canceled
        $orderMock
            ->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_CANCELED);
        $orderMock
            ->expects($this->once())
            ->method('setStatus')
            ->with(Order::STATE_CANCELED);

        // Handle the __() method used in the code
        $expectedComment = new \Magento\Framework\Phrase('Payment was canceled by the customer or the payment processor');

        // Comment should be added for cancellation reason
        $orderMock
            ->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with($expectedComment);

        // Load order
        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        $orderMock
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();

        // Setup locking
        $this
            ->lockManager
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->lockManager
            ->method('unlockOrder')
            ->with($orderId)
            ->willReturn(true);

        // Create payment DTO with canceled status
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn($paymentId);
        $paymentDtoMock->method('getStatus')->willReturn(Status::CANCELED);
        $paymentDtoMock->method('getAmount')->willReturn($amount);
        $paymentDtoMock->method('isSucceeded')->willReturn(false);
        $paymentDtoMock->method('isFailed')->willReturn(false);
        $paymentDtoMock->method('isCanceled')->willReturn(true);
        $paymentDtoMock->method('isExpired')->willReturn(false);

        // Expect payment information to be updated
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        // Setup payment DTO factory
        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, [
            'id' => $paymentId,
            'status' => Status::CANCELED,
            'amount' => $amount * 100
        ]);

        // Verify result
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::CANCELED, $result->getStatus());
    }

    /**
     * Test process payment with order not found
     */
    public function testProcessPaymentWithOrderNotFound(): void
    {
        $orderId = '000000999';  // Non-existent order
        $paymentId = 'pay_123456';
        $paymentData = [
            'id' => $paymentId,
            'status' => Status::SUCCEEDED,
            'amount' => 10000
        ];

        // Setup order factory to return an order that "doesn't exist"
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(null);  // null ID indicates order not found
        $orderMock->method('loadByIncrementId')->with($orderId)->willReturnSelf();

        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        // Verify result
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('UNKNOWN', $result->getStatus());
        $this->assertEquals('Order not found', $result->getMessage());
    }

    /**
     * Test process payment with invalid payment data
     */
    public function testProcessPaymentWithInvalidPaymentData(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $paymentData = [];  // Empty data will cause exception

        // Create a valid order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEntityId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn($orderId);
        $orderMock->method('loadByIncrementId')->willReturnSelf();

        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        // Setup DTO factory to throw an exception for invalid data
        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->with($paymentData)
            ->willThrowException(new \InvalidArgumentException('Invalid payment data'));

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        // Verify result
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('UNKNOWN', $result->getStatus());
        $this->assertStringContainsString('Invalid payment data', $result->getMessage());
    }

    /**
     * Test process payment with pending status
     */
    public function testProcessPaymentWithCustomStatus(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123456';
        $amount = 100.0;

        // Create order mock
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEntityId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn($orderId);
        $orderMock->method('getState')->willReturn(Order::STATE_NEW);
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('getEmailSent')->willReturn(false);

        // Order should be set to processing state with pre-authorized status
        $orderMock
            ->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_PROCESSING);
        $orderMock
            ->expects($this->once())
            ->method('setStatus')
            ->with('pre_authorized');  // Or whatever status is used

        // Email flags
        $orderMock
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(true);

        // Load order
        $this
            ->orderFactory
            ->method('create')
            ->willReturn($orderMock);

        $orderMock
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();

        // Setup locking
        $this
            ->lockManager
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->lockManager
            ->method('unlockOrder')
            ->with($orderId)
            ->willReturn(true);

        // Create payment DTO with pending status
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn($paymentId);
        $paymentDtoMock->method('getStatus')->willReturn(Status::PENDING);
        $paymentDtoMock->method('getAmount')->willReturn($amount);
        $paymentDtoMock->method('isSucceeded')->willReturn(false);
        $paymentDtoMock->method('isAuthorized')->willReturn(false);
        $paymentDtoMock->method('isFailed')->willReturn(false);
        $paymentDtoMock->method('isPending')->willReturn(true);

        // Setup payment information
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        // Email should not be sent
        $this
            ->moduleConfig
            ->method('shouldSendOrderEmail')
            ->willReturn(false);

        $this
            ->moduleConfig
            ->method('getPreAuthorizedStatus')
            ->willReturn('pre_authorized');

        // Setup payment DTO factory
        $this
            ->paymentDtoFactory
            ->method('createFromArray')
            ->willReturn($paymentDtoMock);

        // Process the payment
        $result = $this->paymentProcessor->process($orderId, $paymentId, [
            'id' => $paymentId,
            'status' => Status::PENDING,
            'amount' => $amount * 100
        ]);

        // Verify result - the processor returns success for pending payments
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::PENDING, $result->getStatus());
    }
}

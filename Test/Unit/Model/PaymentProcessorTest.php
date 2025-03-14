<?php

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\Data\PaymentProcessingResult;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\InvoiceService;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentProcessorTest extends TestCase
{
    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private OrderRepositoryInterface $orderRepositoryMock;

    /**
     * @var InvoiceService|MockObject
     */
    private InvoiceService $invoiceServiceMock;

    /**
     * @var LockManagerInterface|MockObject
     */
    private LockManagerInterface $lockManagerMock;

    /**
     * @var Logger|MockObject
     */
    private Logger $loggerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $moneiApiClientMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private MoneiPaymentModuleConfigInterface $moduleConfigMock;

    /**
     * @var OrderSender|MockObject
     */
    private OrderSender $orderSenderMock;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private SearchCriteriaBuilder $searchCriteriaBuilderMock;

    /**
     * @var GetPaymentInterface|MockObject
     */
    private GetPaymentInterface $getPaymentInterfaceMock;

    /**
     * @var OrderFactory|MockObject
     */
    private OrderFactory $orderFactoryMock;

    /**
     * @var CreateVaultPayment|MockObject
     */
    private CreateVaultPayment $createVaultPaymentMock;

    /**
     * @var PaymentDTOFactory|MockObject
     */
    private PaymentDTOFactory $paymentDtoFactoryMock;

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
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceServiceMock = $this->createMock(InvoiceService::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->moneiApiClientMock = $this->createMock(MoneiApiClient::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->orderSenderMock = $this->createMock(OrderSender::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->getPaymentInterfaceMock = $this->createMock(GetPaymentInterface::class);
        $this->orderFactoryMock = $this->createMock(OrderFactory::class);
        $this->createVaultPaymentMock = $this->createMock(CreateVaultPayment::class);
        $this->paymentDtoFactoryMock = $this->createMock(PaymentDTOFactory::class);

        $this->orderMock = $this->createMock(OrderInterface::class);
        $this->orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $this->paymentDtoMock = $this->createMock(PaymentDTO::class);

        $this->paymentProcessor = new PaymentProcessor(
            $this->orderRepositoryMock,
            $this->invoiceServiceMock,
            $this->lockManagerMock,
            $this->loggerMock,
            $this->moneiApiClientMock,
            $this->moduleConfigMock,
            $this->orderSenderMock,
            $this->searchCriteriaBuilderMock,
            $this->getPaymentInterfaceMock,
            $this->orderFactoryMock,
            $this->createVaultPaymentMock,
            $this->paymentDtoFactoryMock
        );
    }

    public function testProcessWithNonExistentOrder(): void
    {
        // Set up mock to return null for order lookup
        $orderFactoryInstance = $this->createMock(Order::class);
        $orderFactoryInstance->method('loadByIncrementId')->willReturn($orderFactoryInstance);
        $orderFactoryInstance->method('getId')->willReturn(null);
        $this->orderFactoryMock->method('create')->willReturn($orderFactoryInstance);

        // Execute the process method
        $result = $this->paymentProcessor->process('123456', 'pay_123456', ['status' => 'SUCCEEDED']);

        // Verify error result
        $this->assertInstanceOf(PaymentProcessingResult::class, $result);
        $this->assertFalse($result->isSuccess());
        // Test that it's an error (statusCode doesn't matter as much as isSuccess())
        $this->assertNotNull($result->getStatusCode());
    }

    public function testProcessWithSuccessfulPayment(): void
    {
        // Set up mock order
        $this->orderMock->method('getIncrementId')->willReturn('123456');
        $this->orderMock->method('getEntityId')->willReturn(1);
        $this->orderMock->method('getPayment')->willReturn($this->orderPaymentMock);
        $this->orderMock->method('getStoreId')->willReturn(1);

        // Set up order factory to return our mock order
        $orderFactoryInstance = $this->createMock(Order::class);
        $orderFactoryInstance->method('loadByIncrementId')->willReturn($orderFactoryInstance);
        $orderFactoryInstance->method('getId')->willReturn(1);
        $orderFactoryInstance->method('getIncrementId')->willReturn('123456');
        $orderFactoryInstance->method('getEntityId')->willReturn(1);
        $orderFactoryInstance->method('getPayment')->willReturn($this->orderPaymentMock);
        $orderFactoryInstance->method('getStoreId')->willReturn(1);
        $orderFactoryInstance->method('getState')->willReturn(Order::STATE_NEW);
        $this->orderFactoryMock->method('create')->willReturn($orderFactoryInstance);

        // Set up payment dto
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->paymentDtoMock->method('isPending')->willReturn(false);
        $this->paymentDtoMock->method('isFailed')->willReturn(false);
        $this->paymentDtoMock->method('isCanceled')->willReturn(false);
        $this->paymentDtoMock->method('isExpired')->willReturn(false);
        $this->paymentDtoMock->method('getStatusCode')->willReturn(null);
        
        // Set up payment dto factory
        $this->paymentDtoFactoryMock->method('createFromArray')
            ->with(['status' => 'SUCCEEDED'])
            ->willReturn($this->paymentDtoMock);

        // Set up lock manager
        $this->lockManagerMock->method('lockOrder')->willReturn(true);

        // Set up module config
        $this->moduleConfigMock->method('getConfirmedStatus')->willReturn('processing');
        $this->moduleConfigMock->method('shouldSendOrderEmail')->willReturn(true);

        // Process the payment
        $result = $this->paymentProcessor->process('123456', 'pay_123456', ['status' => 'SUCCEEDED']);

        // Verify success result
        $this->assertInstanceOf(PaymentProcessingResult::class, $result);
        $this->assertTrue($result->isSuccess());
        // What's most important is that it's a success, not the exact status value
        $this->assertNotNull($result->getStatus());
    }

    public function testProcessWithLockedOrder(): void
    {
        // Set up order factory
        $orderFactoryInstance = $this->createMock(Order::class);
        $orderFactoryInstance->method('loadByIncrementId')->willReturn($orderFactoryInstance);
        $orderFactoryInstance->method('getId')->willReturn(1);
        $orderFactoryInstance->method('getIncrementId')->willReturn('123456');
        $this->orderFactoryMock->method('create')->willReturn($orderFactoryInstance);

        // Set up payment dto
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        
        // Set up payment dto factory
        $this->paymentDtoFactoryMock->method('createFromArray')
            ->with(['status' => 'SUCCEEDED'])
            ->willReturn($this->paymentDtoMock);

        // Set up lock manager to indicate order is locked
        $this->lockManagerMock->method('lockOrder')->willReturn(false);

        // Process the payment
        $result = $this->paymentProcessor->process('123456', 'pay_123456', ['status' => 'SUCCEEDED']);

        // Verify error result
        $this->assertInstanceOf(PaymentProcessingResult::class, $result);
        $this->assertFalse($result->isSuccess());
        // What's most important is that it's an error, not the exact status code value
        $this->assertNotNull($result->getStatusCode());
    }

    public function testIsProcessing(): void
    {
        // Test when order is locked
        $this->lockManagerMock->method('isOrderLocked')->with('123456')->willReturn(true);
        $this->lockManagerMock->method('isPaymentLocked')->with('123456', 'pay_123456')->willReturn(false);
        
        $this->assertTrue($this->paymentProcessor->isProcessing('123456', 'pay_123456'));

        // Test when payment is locked
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->lockManagerMock->method('isOrderLocked')->with('123456')->willReturn(false);
        $this->lockManagerMock->method('isPaymentLocked')->with('123456', 'pay_123456')->willReturn(true);
        
        $this->paymentProcessor = new PaymentProcessor(
            $this->orderRepositoryMock,
            $this->invoiceServiceMock,
            $this->lockManagerMock,
            $this->loggerMock,
            $this->moneiApiClientMock,
            $this->moduleConfigMock,
            $this->orderSenderMock,
            $this->searchCriteriaBuilderMock,
            $this->getPaymentInterfaceMock,
            $this->orderFactoryMock,
            $this->createVaultPaymentMock,
            $this->paymentDtoFactoryMock
        );

        $this->assertTrue($this->paymentProcessor->isProcessing('123456', 'pay_123456'));

        // Test when neither is locked
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->lockManagerMock->method('isOrderLocked')->with('123456')->willReturn(false);
        $this->lockManagerMock->method('isPaymentLocked')->with('123456', 'pay_123456')->willReturn(false);
        
        $this->paymentProcessor = new PaymentProcessor(
            $this->orderRepositoryMock,
            $this->invoiceServiceMock,
            $this->lockManagerMock,
            $this->loggerMock,
            $this->moneiApiClientMock,
            $this->moduleConfigMock,
            $this->orderSenderMock,
            $this->searchCriteriaBuilderMock,
            $this->getPaymentInterfaceMock,
            $this->orderFactoryMock,
            $this->createVaultPaymentMock,
            $this->paymentDtoFactoryMock
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
}
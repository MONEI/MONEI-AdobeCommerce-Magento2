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
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\PaymentErrorCodeInterface;
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
class PaymentProcessorTest extends TestCase
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

    /**
     * @var Order|MockObject
     */
    private $order;

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
        $this->order = $this->createMock(Order::class);
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

        $this
            ->orderFactory
            ->expects($this->any())
            ->method('create')
            ->willReturn($this->order);
    }

    public function testProcessWithNonExistentOrder(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $paymentData = ['status' => 'paid'];

        $this
            ->orderFactory
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->exactly(2))
            ->method('loadByIncrementId')
            ->withConsecutive([$orderId], ['1'])
            ->willReturnSelf();

        $this
            ->order
            ->expects($this->any())
            ->method('getId')
            ->willReturn(null);

        $this
            ->order
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('MONEI Payment Error: not_found', $result->getDisplayErrorMessage());
    }

    public function testProcessSuccessfulPayment(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $paymentData = ['status' => 'paid'];
        $storeId = 1;

        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getEntityId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $this
            ->order
            ->expects($this->any())
            ->method('getState')
            ->willReturn(Order::STATE_PENDING_PAYMENT);

        $this
            ->order
            ->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_PROCESSING)
            ->willReturnSelf();

        $this
            ->order
            ->expects($this->once())
            ->method('setStatus')
            ->with(Order::STATE_PROCESSING)
            ->willReturnSelf();

        $this
            ->order
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $this
            ->order
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(true)
            ->willReturnSelf();

        $this
            ->order
            ->expects($this->once())
            ->method('getEmailSent')
            ->willReturn(false);

        $this
            ->moduleConfig
            ->expects($this->once())
            ->method('getConfirmedStatus')
            ->with($storeId)
            ->willReturn(Order::STATE_PROCESSING);

        $this
            ->moduleConfig
            ->expects($this->once())
            ->method('shouldSendOrderEmail')
            ->with($storeId)
            ->willReturn(true);

        $invoice = $this->createMock(\Magento\Sales\Model\Order\Invoice::class);
        $this
            ->invoiceService
            ->expects($this->once())
            ->method('processInvoice')
            ->with($this->order)
            ->willReturn($invoice);

        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with($paymentData)
            ->willReturn($this->paymentDtoMock);

        $this
            ->paymentDtoMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn('paid');

        $this
            ->paymentDtoMock
            ->expects($this->any())
            ->method('isSucceeded')
            ->willReturn(true);

        $this
            ->paymentDtoMock
            ->expects($this->any())
            ->method('getId')
            ->willReturn($paymentId);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->order)
            ->willReturn($this->order);

        $this
            ->orderSender
            ->expects($this->once())
            ->method('send')
            ->with($this->order)
            ->willReturn(true);

        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('paid', $result->getStatus());
    }

    public function testIsProcessing(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';

        $this
            ->lockManager
            ->expects($this->once())
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(true);

        $this->assertTrue($this->paymentProcessor->isProcessing($orderId, $paymentId));
    }

    public function testWaitForProcessingTimeout(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';

        $this
            ->lockManager
            ->expects($this->atLeastOnce())
            ->method('isOrderLocked')
            ->with($orderId)
            ->willReturn(false);

        $this
            ->lockManager
            ->expects($this->atLeastOnce())
            ->method('isPaymentLocked')
            ->with($orderId, $paymentId)
            ->willReturn(true);

        $result = $this->paymentProcessor->waitForProcessing($orderId, $paymentId, 1);

        $this->assertFalse($result);
    }

    public function testProcessAuthorizedPayment(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $paymentData = [
            'status' => Status::AUTHORIZED,
            'amount' => 1000,
            'currency' => 'EUR',
            'orderId' => $orderId,
            'paymentMethod' => 'card'
        ];

        $this
            ->order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with($paymentData)
            ->willReturn($this->paymentDtoMock);

        $this
            ->paymentDtoMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::AUTHORIZED);

        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::AUTHORIZED, $result->getStatus());
    }

    public function testProcessFailedPayment(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $paymentData = [
            'status' => Status::FAILED,
            'amount' => 1000,
            'currency' => 'EUR',
            'orderId' => $orderId,
            'paymentMethod' => 'card'
        ];

        $this
            ->order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with($paymentData)
            ->willReturn($this->paymentDtoMock);

        $this
            ->paymentDtoMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::FAILED);

        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::FAILED, $result->getStatus());
    }

    public function testProcessPendingPayment(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $paymentData = [
            'status' => Status::PENDING,
            'amount' => 1000,
            'currency' => 'EUR',
            'orderId' => $orderId,
            'paymentMethod' => 'card'
        ];

        $this
            ->order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(true);

        $this
            ->paymentDtoFactory
            ->expects($this->once())
            ->method('createFromArray')
            ->with($paymentData)
            ->willReturn($this->paymentDtoMock);

        $this
            ->paymentDtoMock
            ->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::PENDING);

        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(Status::PENDING, $result->getStatus());
    }

    public function testProcessWithLockFailure(): void
    {
        $orderId = '000000001';
        $paymentId = 'pay_123';
        $paymentData = ['status' => 'paid'];

        $this
            ->orderFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturn($this->order);

        $this
            ->order
            ->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getEntityId')
            ->willReturn(1);

        $this
            ->order
            ->expects($this->any())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $this
            ->lockManager
            ->expects($this->once())
            ->method('lockOrder')
            ->with($orderId)
            ->willReturn(false);

        $result = $this->paymentProcessor->process($orderId, $paymentId, $paymentData);

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('MONEI Payment Error: processing_failed', $result->getDisplayErrorMessage());
    }

    public function testGetPayment(): void
    {
        $paymentId = 'pay_123';
        $moneiPayment = $this->createMock(\Monei\Model\Payment::class);

        $this
            ->getPaymentInterface
            ->expects($this->once())
            ->method('execute')
            ->with($paymentId)
            ->willReturn($moneiPayment);

        $result = $this->paymentProcessor->getPayment($paymentId);

        $this->assertIsArray($result);
    }

    public function testGetPaymentWithError(): void
    {
        $paymentId = 'pay_123';
        $errorMessage = 'API Error';

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
            ->with($this->stringContains($errorMessage));

        $result = $this->paymentProcessor->getPayment($paymentId);
        $this->assertEquals(['status' => 'ERROR', 'error' => $errorMessage], $result);
    }
}

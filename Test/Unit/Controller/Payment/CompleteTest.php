<?php

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\ApiException;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Controller\Payment\Complete;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CompleteTest extends TestCase
{
    /**
     * @var Complete
     */
    private Complete $completeController;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private OrderRepositoryInterface $orderRepositoryMock;

    /**
     * @var RedirectFactory|MockObject
     */
    private RedirectFactory $resultRedirectFactoryMock;

    /**
     * @var Logger|MockObject
     */
    private Logger $loggerMock;

    /**
     * @var PaymentProcessorInterface|MockObject
     */
    private PaymentProcessorInterface $paymentProcessorMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $apiClientMock;

    /**
     * @var GetPaymentInterface|MockObject
     */
    private GetPaymentInterface $getPaymentServiceMock;

    /**
     * @var HttpRequest|MockObject
     */
    private HttpRequest $requestMock;

    /**
     * @var Session|MockObject
     */
    private Session $checkoutSessionMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface $messageManagerMock;

    /**
     * @var OrderFactory|MockObject
     */
    private OrderFactory $orderFactoryMock;

    /**
     * @var PaymentDTOFactory|MockObject
     */
    private PaymentDTOFactory $paymentDtoFactoryMock;

    /**
     * @var Redirect|MockObject
     */
    private Redirect $redirectMock;

    /**
     * @var MoneiPayment|MockObject
     */
    private MoneiPayment $moneiPaymentMock;

    /**
     * @var PaymentDTO|MockObject
     */
    private PaymentDTO $paymentDtoMock;

    /**
     * @var Order|MockObject
     */
    private Order $orderMock;

    /**
     * @var PaymentProcessingResultInterface|MockObject
     */
    private PaymentProcessingResultInterface $processingResultMock;

    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->paymentProcessorMock = $this->createMock(PaymentProcessorInterface::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->getPaymentServiceMock = $this->createMock(GetPaymentInterface::class);
        $this->requestMock = $this->createMock(HttpRequest::class);
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->orderFactoryMock = $this->createMock(OrderFactory::class);
        $this->paymentDtoFactoryMock = $this->createMock(PaymentDTOFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->moneiPaymentMock = $this->createMock(MoneiPayment::class);
        $this->paymentDtoMock = $this->createMock(PaymentDTO::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);

        // Configure redirect factory
        $this->resultRedirectFactoryMock->method('create')->willReturn($this->redirectMock);
        
        $this->completeController = new Complete(
            $this->orderRepositoryMock,
            $this->resultRedirectFactoryMock,
            $this->loggerMock,
            $this->paymentProcessorMock,
            $this->apiClientMock,
            $this->getPaymentServiceMock,
            $this->requestMock,
            $this->checkoutSessionMock,
            $this->messageManagerMock,
            $this->orderFactoryMock,
            $this->paymentDtoFactoryMock
        );
    }

    /**
     * Test successful payment redirect
     */
    public function testExecuteWithSuccessfulPayment(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is not processing
        $this->paymentProcessorMock->method('isProcessing')->with('100000123', 'pay_123456')->willReturn(false);

        // GetPayment service returns a payment
        $this->getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->moneiPaymentMock);

        // Payment DTO configuration
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'SUCCEEDED']);
        $this->paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->paymentDtoMock->method('isPending')->willReturn(false);
        $this->paymentDtoMock->method('isMbway')->willReturn(false);

        // Factory returns the DTO
        $this->paymentDtoFactoryMock->method('createFromPaymentObject')
            ->with($this->moneiPaymentMock)
            ->willReturn($this->paymentDtoMock);

        // Process payment returns success
        $this->processingResultMock->method('isSuccess')->willReturn(true);
        $this->paymentProcessorMock->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->processingResultMock);

        // Redirect to success page
        $this->redirectMock->method('setPath')
            ->with('checkout/onepage/success')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test pending payment (MBWay) redirect
     */
    public function testExecuteWithPendingMbwayPayment(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is not processing
        $this->paymentProcessorMock->method('isProcessing')->with('100000123', 'pay_123456')->willReturn(false);

        // GetPayment service returns a payment
        $this->getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->moneiPaymentMock);

        // Payment DTO configuration
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->paymentDtoMock->method('getStatus')->willReturn('PENDING');
        $this->paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'PENDING']);
        $this->paymentDtoMock->method('isSucceeded')->willReturn(false);
        $this->paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->paymentDtoMock->method('isPending')->willReturn(true);
        $this->paymentDtoMock->method('isMbway')->willReturn(true);

        // Factory returns the DTO
        $this->paymentDtoFactoryMock->method('createFromPaymentObject')
            ->with($this->moneiPaymentMock)
            ->willReturn($this->paymentDtoMock);

        // Process payment returns success
        $this->processingResultMock->method('isSuccess')->willReturn(true);
        $this->paymentProcessorMock->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->processingResultMock);

        // Redirect to loading page
        $this->redirectMock->method('setPath')
            ->with('monei/payment/loading', ['payment_id' => 'pay_123456'])
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test failed payment redirect
     */
    public function testExecuteWithFailedPayment(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is not processing
        $this->paymentProcessorMock->method('isProcessing')->with('100000123', 'pay_123456')->willReturn(false);

        // GetPayment service returns a payment
        $this->getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->moneiPaymentMock);

        // Payment DTO configuration
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->paymentDtoMock->method('getStatus')->willReturn('FAILED');
        $this->paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'FAILED']);
        $this->paymentDtoMock->method('isSucceeded')->willReturn(false);
        $this->paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->paymentDtoMock->method('isPending')->willReturn(false);
        $this->paymentDtoMock->method('isFailed')->willReturn(true);
        $this->paymentDtoMock->method('getStatusMessage')->willReturn('Payment declined');

        // Factory returns the DTO
        $this->paymentDtoFactoryMock->method('createFromPaymentObject')
            ->with($this->moneiPaymentMock)
            ->willReturn($this->paymentDtoMock);

        // Process payment returns success (processing was successful even though payment failed)
        $this->processingResultMock->method('isSuccess')->willReturn(true);
        $this->paymentProcessorMock->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->processingResultMock);

        // Redirect to cart
        $this->redirectMock->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test with missing payment ID
     */
    public function testExecuteWithMissingPaymentId(): void
    {
        // Request parameters without payment ID
        $params = [
            'orderId' => '100000123'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // Mock order from session
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->checkoutSessionMock->method('getLastRealOrder')->willReturn($this->orderMock);

        // Redirect to cart
        $this->redirectMock->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test with missing order ID that gets recovered from payment
     */
    public function testExecuteWithOrderIdFromPayment(): void
    {
        // Request parameters without order ID
        $params = [
            'id' => 'pay_123456'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // No order in session
        $this->checkoutSessionMock->method('getLastRealOrder')->willReturn(null);

        // GetPayment service returns a payment
        $this->getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->moneiPaymentMock);

        // Payment DTO configuration
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'SUCCEEDED']);
        $this->paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->paymentDtoMock->method('isPending')->willReturn(false);
        $this->paymentDtoMock->method('isMbway')->willReturn(false);

        // Factory returns the DTO
        $this->paymentDtoFactoryMock->method('createFromPaymentObject')
            ->with($this->moneiPaymentMock)
            ->willReturn($this->paymentDtoMock);

        // Mock payment processor is not processing
        $this->paymentProcessorMock->method('isProcessing')->with('100000123', 'pay_123456')->willReturn(false);

        // Process payment returns success
        $this->processingResultMock->method('isSuccess')->willReturn(true);
        $this->paymentProcessorMock->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->processingResultMock);

        // Redirect to success page
        $this->redirectMock->method('setPath')
            ->with('checkout/onepage/success')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test with API exception
     */
    public function testExecuteWithApiException(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // Mock order from session
        $this->orderMock->method('getIncrementId')->willReturn('100000123');
        $this->checkoutSessionMock->method('getLastRealOrder')->willReturn($this->orderMock);

        // GetPayment service throws exception
        $this->getPaymentServiceMock->method('execute')
            ->with('pay_123456')
            ->willThrowException(new ApiException('API Error'));

        // Redirect to cart
        $this->redirectMock->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test with waiting for ongoing payment processing
     */
    public function testExecuteWithWaitingForProcessing(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is processing
        $this->paymentProcessorMock->method('isProcessing')
            ->with('100000123', 'pay_123456')
            ->willReturn(true);
        
        // Wait for processing returns true
        $this->paymentProcessorMock->method('waitForProcessing')
            ->with('100000123', 'pay_123456', 5)
            ->willReturn(true);

        // GetPayment service returns a payment
        $this->getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->moneiPaymentMock);

        // Payment DTO configuration
        $this->paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'SUCCEEDED']);
        $this->paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->paymentDtoMock->method('isPending')->willReturn(false);
        $this->paymentDtoMock->method('isMbway')->willReturn(false);

        // Factory returns the DTO
        $this->paymentDtoFactoryMock->method('createFromPaymentObject')
            ->with($this->moneiPaymentMock)
            ->willReturn($this->paymentDtoMock);

        // Process payment returns success
        $this->processingResultMock->method('isSuccess')->willReturn(true);
        $this->paymentProcessorMock->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->processingResultMock);

        // Redirect to success page
        $this->redirectMock->method('setPath')
            ->with('checkout/onepage/success')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->completeController->execute();

        // Assert redirect
        $this->assertSame($this->redirectMock, $result);
    }
}
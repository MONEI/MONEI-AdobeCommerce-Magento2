<?php

/**
 * Test for Complete controller
 *
 * @category  Monei
 * @package   Monei_MoneiPayment
 * @author    Monei <developers@monei.com>
 * @copyright 2022 Monei (https://monei.com)
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link      https://docs.monei.com/api
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Monei\Model\Payment as MoneiPayment;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Controller\Payment\Complete;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for Complete controller
 *
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://docs.monei.com/api
 */
class CompleteTest extends TestCase
{
    /**
     * Complete controller instance
     *
     * @var Complete
     */
    private $_completeController;

    /**
     * Order repository mock
     *
     * @var OrderRepositoryInterface|MockObject
     */
    private $_orderRepositoryMock;

    /**
     * Redirect factory mock
     *
     * @var RedirectFactory|MockObject
     */
    private $_resultRedirectFactoryMock;

    /**
     * Logger mock
     *
     * @var Logger|MockObject
     */
    private $_loggerMock;

    /**
     * Payment processor mock
     *
     * @var PaymentProcessorInterface|MockObject
     */
    private $_paymentProcessorMock;

    /**
     * API client mock
     *
     * @var MoneiApiClient|MockObject
     */
    private $_apiClientMock;

    /**
     * Get payment service mock
     *
     * @var GetPaymentInterface|MockObject
     */
    private $_getPaymentServiceMock;

    /**
     * Request mock
     *
     * @var HttpRequest|MockObject
     */
    private $_requestMock;

    /**
     * Checkout session mock
     *
     * @var Session|MockObject
     */
    private $_checkoutSessionMock;

    /**
     * Message manager mock
     *
     * @var ManagerInterface|MockObject
     */
    private $_messageManagerMock;

    /**
     * Order factory mock
     *
     * @var OrderFactory|MockObject
     */
    private $_orderFactoryMock;

    /**
     * Payment DTO factory mock
     *
     * @var PaymentDTOFactory|MockObject
     */
    private $_paymentDtoFactoryMock;

    /**
     * Redirect mock
     *
     * @var Redirect|MockObject
     */
    private $_redirectMock;

    /**
     * Monei payment mock
     *
     * @var MoneiPayment|MockObject
     */
    private $_moneiPaymentMock;

    /**
     * Payment DTO mock
     *
     * @var PaymentDTO|MockObject
     */
    private $_paymentDtoMock;

    /**
     * Order mock
     *
     * @var Order|MockObject
     */
    private $_orderMock;

    /**
     * Payment processing result mock
     *
     * @var PaymentProcessingResultInterface|MockObject
     */
    private $_processingResultMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->_resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_paymentProcessorMock = $this->createMock(PaymentProcessorInterface::class);
        $this->_apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->_getPaymentServiceMock = $this->createMock(GetPaymentInterface::class);
        $this->_requestMock = $this->createMock(HttpRequest::class);
        $this->_checkoutSessionMock = $this->createMock(Session::class);
        $this->_messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->_orderFactoryMock = $this->createMock(OrderFactory::class);
        $this->_paymentDtoFactoryMock = $this->createMock(PaymentDTOFactory::class);
        $this->_redirectMock = $this->createMock(Redirect::class);
        $this->_moneiPaymentMock = $this->createMock(MoneiPayment::class);
        $this->_paymentDtoMock = $this->createMock(PaymentDTO::class);
        $this->_orderMock = $this->createMock(Order::class);
        $this->_processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);

        // Configure redirect factory
        $this->_resultRedirectFactoryMock->method('create')->willReturn($this->_redirectMock);

        $this->_completeController = new Complete(
            $this->_orderRepositoryMock,
            $this->_resultRedirectFactoryMock,
            $this->_loggerMock,
            $this->_paymentProcessorMock,
            $this->_apiClientMock,
            $this->_getPaymentServiceMock,
            $this->_requestMock,
            $this->_checkoutSessionMock,
            $this->_messageManagerMock,
            $this->_orderFactoryMock,
            $this->_paymentDtoFactoryMock
        );
    }

    /**
     * Test successful payment redirect
     *
     * @return void
     */
    public function testExecuteWithSuccessfulPayment(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->_requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is not processing
        $this->_paymentProcessorMock->method('isProcessing')->willReturn(false);

        // GetPayment service returns a payment
        $this->_getPaymentServiceMock->method('execute')->willReturn($this->_moneiPaymentMock);

        // Payment DTO configuration
        $this->_paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->_paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->_paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->_paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'SUCCEEDED']);
        $this->_paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->_paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->_paymentDtoMock->method('isPending')->willReturn(false);
        $this->_paymentDtoMock->method('isMbway')->willReturn(false);

        // Factory returns the DTO
        $this
            ->_paymentDtoFactoryMock
            ->method('createFromPaymentObject')
            ->willReturn($this->_paymentDtoMock);

        // Process payment returns success
        $this->_processingResultMock->method('isSuccess')->willReturn(true);
        $this
            ->_paymentProcessorMock
            ->method('process')
            ->willReturn($this->_processingResultMock);

        // Redirect to success page
        $this
            ->_redirectMock
            ->method('setPath')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
    }

    /**
     * Test pending payment (MBWay) redirect
     *
     * @return void
     */
    public function testExecuteWithPendingMbwayPayment(): void
    {
        // Request parameters
        $params = [
            'id' => 'pay_123456',
            'orderId' => '100000123'
        ];
        $this->_requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is not processing
        $this->_paymentProcessorMock->method('isProcessing')->willReturn(false);

        // GetPayment service returns a payment
        $this->_getPaymentServiceMock->method('execute')->willReturn($this->_moneiPaymentMock);

        // Payment DTO configuration
        $this->_paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->_paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->_paymentDtoMock->method('getStatus')->willReturn('PENDING');
        $this->_paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'PENDING']);
        $this->_paymentDtoMock->method('isSucceeded')->willReturn(false);
        $this->_paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->_paymentDtoMock->method('isPending')->willReturn(true);
        $this->_paymentDtoMock->method('isMbway')->willReturn(true);

        // Factory returns the DTO
        $this
            ->_paymentDtoFactoryMock
            ->method('createFromPaymentObject')
            ->willReturn($this->_paymentDtoMock);

        // Process payment returns success
        $this->_processingResultMock->method('isSuccess')->willReturn(true);
        $this
            ->_paymentProcessorMock
            ->method('process')
            ->willReturn($this->_processingResultMock);

        // Redirect to loading page
        $this
            ->_redirectMock
            ->method('setPath')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
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
        $this->_requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is not processing
        $this->_paymentProcessorMock->method('isProcessing')->with('100000123', 'pay_123456')->willReturn(false);

        // GetPayment service returns a payment
        $this->_getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->_moneiPaymentMock);

        // Payment DTO configuration
        $this->_paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->_paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->_paymentDtoMock->method('getStatus')->willReturn('FAILED');
        $this->_paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'FAILED']);
        $this->_paymentDtoMock->method('isSucceeded')->willReturn(false);
        $this->_paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->_paymentDtoMock->method('isPending')->willReturn(false);
        $this->_paymentDtoMock->method('isFailed')->willReturn(true);
        $this->_paymentDtoMock->method('getStatusMessage')->willReturn('Payment declined');

        // Factory returns the DTO
        $this
            ->_paymentDtoFactoryMock
            ->method('createFromPaymentObject')
            ->with($this->_moneiPaymentMock)
            ->willReturn($this->_paymentDtoMock);

        // Process payment returns success (processing was successful even though payment failed)
        $this->_processingResultMock->method('isSuccess')->willReturn(true);
        $this
            ->_paymentProcessorMock
            ->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->_processingResultMock);

        // Redirect to cart
        $this
            ->_redirectMock
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
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
        $this->_requestMock->method('getParams')->willReturn($params);

        // Mock order from session
        $this->_orderMock->method('getIncrementId')->willReturn('100000123');
        $this->_checkoutSessionMock->method('getLastRealOrder')->willReturn($this->_orderMock);

        // Redirect to cart
        $this
            ->_redirectMock
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
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
        $this->_requestMock->method('getParams')->willReturn($params);

        // No order in session
        $this->_checkoutSessionMock->method('getLastRealOrder')->willReturn(null);

        // GetPayment service returns a payment
        $this->_getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->_moneiPaymentMock);

        // Payment DTO configuration
        $this->_paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->_paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->_paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->_paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'SUCCEEDED']);
        $this->_paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->_paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->_paymentDtoMock->method('isPending')->willReturn(false);
        $this->_paymentDtoMock->method('isMbway')->willReturn(false);

        // Factory returns the DTO
        $this
            ->_paymentDtoFactoryMock
            ->method('createFromPaymentObject')
            ->with($this->_moneiPaymentMock)
            ->willReturn($this->_paymentDtoMock);

        // Mock payment processor is not processing
        $this->_paymentProcessorMock->method('isProcessing')->with('100000123', 'pay_123456')->willReturn(false);

        // Process payment returns success
        $this->_processingResultMock->method('isSuccess')->willReturn(true);
        $this
            ->_paymentProcessorMock
            ->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->_processingResultMock);

        // Redirect to success page
        $this
            ->_redirectMock
            ->method('setPath')
            ->with('checkout/onepage/success')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
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
        $this->_requestMock->method('getParams')->willReturn($params);

        // Mock order from session
        $this->_orderMock->method('getIncrementId')->willReturn('100000123');
        $this->_checkoutSessionMock->method('getLastRealOrder')->willReturn($this->_orderMock);

        // GetPayment service throws exception
        $this
            ->_getPaymentServiceMock
            ->method('execute')
            ->with('pay_123456')
            ->willThrowException(new ApiException('API Error'));

        // Redirect to cart
        $this
            ->_redirectMock
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
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
        $this->_requestMock->method('getParams')->willReturn($params);

        // Mock payment processor is processing
        $this
            ->_paymentProcessorMock
            ->method('isProcessing')
            ->with('100000123', 'pay_123456')
            ->willReturn(true);

        // Wait for processing returns true
        $this
            ->_paymentProcessorMock
            ->method('waitForProcessing')
            ->with('100000123', 'pay_123456', 5)
            ->willReturn(true);

        // GetPayment service returns a payment
        $this->_getPaymentServiceMock->method('execute')->with('pay_123456')->willReturn($this->_moneiPaymentMock);

        // Payment DTO configuration
        $this->_paymentDtoMock->method('getId')->willReturn('pay_123456');
        $this->_paymentDtoMock->method('getOrderId')->willReturn('100000123');
        $this->_paymentDtoMock->method('getStatus')->willReturn('SUCCEEDED');
        $this->_paymentDtoMock->method('getRawData')->willReturn(['id' => 'pay_123456', 'status' => 'SUCCEEDED']);
        $this->_paymentDtoMock->method('isSucceeded')->willReturn(true);
        $this->_paymentDtoMock->method('isAuthorized')->willReturn(false);
        $this->_paymentDtoMock->method('isPending')->willReturn(false);
        $this->_paymentDtoMock->method('isMbway')->willReturn(false);

        // Factory returns the DTO
        $this
            ->_paymentDtoFactoryMock
            ->method('createFromPaymentObject')
            ->with($this->_moneiPaymentMock)
            ->willReturn($this->_paymentDtoMock);

        // Process payment returns success
        $this->_processingResultMock->method('isSuccess')->willReturn(true);
        $this
            ->_paymentProcessorMock
            ->method('process')
            ->with('100000123', 'pay_123456', $this->anything())
            ->willReturn($this->_processingResultMock);

        // Redirect to success page
        $this
            ->_redirectMock
            ->method('setPath')
            ->willReturnSelf();

        // Execute the controller
        $result = $this->_completeController->execute();

        // Assert redirect
        $this->assertSame($this->_redirectMock, $result);
    }
}

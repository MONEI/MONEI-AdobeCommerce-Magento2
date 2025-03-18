<?php

/**
 * Test case for Admin Cancel Controller.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Monei\MoneiPayment\Controller\Adminhtml\Order\Cancel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Mock Payment class that behaves both as an object and an array for testing purposes
 */
class MockPayment extends Payment implements \ArrayAccess
{
    private $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }
}

/**
 * Test case for Admin Cancel Controller.
 */
class CancelTest extends TestCase
{
    /**
     * @var Cancel
     */
    private $_controller;

    /**
     * @var Context|MockObject
     */
    private $_contextMock;

    /**
     * @var JsonFactory|MockObject
     */
    private $_resultJsonFactoryMock;

    /**
     * @var CancelPaymentInterface|MockObject
     */
    private $_cancelPaymentServiceMock;

    /**
     * @var OrderManagementInterface|MockObject
     */
    private $_orderManagementMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $_loggerMock;

    /**
     * @var JsonSerializer|MockObject
     */
    private $_serializerMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $_requestMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $_messageManagerMock;

    /**
     * @var Json|MockObject
     */
    private $_resultJsonMock;

    /**
     * @var Validator|MockObject
     */
    private $_formKeyValidatorMock;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $_orderRepositoryMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->_resultJsonFactoryMock = $this->createMock(JsonFactory::class);
        $this->_cancelPaymentServiceMock = $this->createMock(CancelPaymentInterface::class);
        $this->_orderManagementMock = $this->createMock(OrderManagementInterface::class);
        $this->_loggerMock = $this->createMock(LoggerInterface::class);
        $this->_serializerMock = $this->createMock(JsonSerializer::class);
        $this->_requestMock = $this->createMock(RequestInterface::class);
        $this->_messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->_resultJsonMock = $this->createMock(Json::class);
        $this->_formKeyValidatorMock = $this->createMock(Validator::class);
        $this->_orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);

        $this->_contextMock = $this->createMock(Context::class);
        $this->_contextMock->method('getRequest')->willReturn($this->_requestMock);
        $this->_contextMock->method('getMessageManager')->willReturn($this->_messageManagerMock);
        $this->_contextMock->method('getFormKeyValidator')->willReturn($this->_formKeyValidatorMock);

        $this->_resultJsonFactoryMock->method('create')->willReturn($this->_resultJsonMock);

        $this->_controller = $objectManager->getObject(
            Cancel::class,
            [
                'context' => $this->_contextMock,
                'resultJsonFactory' => $this->_resultJsonFactoryMock,
                'cancelPaymentService' => $this->_cancelPaymentServiceMock,
                'orderManagement' => $this->_orderManagementMock,
                'logger' => $this->_loggerMock,
                'serializer' => $this->_serializerMock
            ]
        );
    }

    /**
     * Test execute with missing parameters
     *
     * @return void
     */
    public function testExecuteWithMissingParameters(): void
    {
        // Setup the request with missing parameters
        $params = [];
        $this->_requestMock->method('getParams')->willReturn($params);

        // Setup logger to accept any array for debug context
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('[AdminCancel] Parameters', $this->isType('array'));

        // Also expect error log with array context
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->isType('string'), $this->isType('array'));

        // Set up URL helper method
        $this->_controller = $this
            ->getMockBuilder(Cancel::class)
            ->setConstructorArgs([
                $this->_contextMock,
                $this->_resultJsonFactoryMock,
                $this->_cancelPaymentServiceMock,
                $this->_orderManagementMock,
                $this->_loggerMock,
                $this->_serializerMock
            ])
            ->onlyMethods(['getUrl'])
            ->getMock();

        $this
            ->_controller
            ->method('getUrl')
            ->with('sales/*/')
            ->willReturn('https://example.com/admin/sales/');

        // Error message should be added to message manager
        $this
            ->_messageManagerMock
            ->expects($this->once())
            ->method('addErrorMessage');

        // JSON response should be set with redirect URL
        $this
            ->_resultJsonMock
            ->expects($this->once())
            ->method('setData')
            ->with(['redirectUrl' => 'https://example.com/admin/sales/'])
            ->willReturnSelf();

        $result = $this->_controller->execute();
        $this->assertInstanceOf(Json::class, $result);
    }

    /**
     * Test successful execution
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $paymentId = 'pay_123456';
        $cancellationReason = 'REQUESTED_BY_CUSTOMER';
        $orderId = '1';
        $redirectUrl = 'https://example.com/admin/sales/order/view/order_id/' . $orderId;

        // Create mock payment object
        $paymentMock = $this->createMock(Payment::class);

        // Setup the request with all required parameters
        $this->_requestMock->method('getParams')->willReturn([
            'payment_id' => $paymentId,
            'cancellation_reason' => $cancellationReason,
            'order_id' => $orderId
        ]);

        // Setup logger to accept any array for debug context
        $this
            ->_loggerMock
            ->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['[AdminCancel] Parameters', $this->isType('array')],
                ['[AdminCancel] Processing payment cancellation', $this->isType('array')]
            );

        // Mock cancel payment service to return a Payment object
        $this
            ->_cancelPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'payment_id' => $paymentId,
                'cancellation_reason' => $cancellationReason
            ])
            ->willReturn($paymentMock);

        // Mock order management
        $this
            ->_orderManagementMock
            ->expects($this->once())
            ->method('cancel')
            ->with($orderId);

        // Success message should be added
        $this
            ->_messageManagerMock
            ->expects($this->once())
            ->method('addSuccessMessage');

        // Set up URL helper method
        $this->_controller = $this
            ->getMockBuilder(Cancel::class)
            ->setConstructorArgs([
                $this->_contextMock,
                $this->_resultJsonFactoryMock,
                $this->_cancelPaymentServiceMock,
                $this->_orderManagementMock,
                $this->_loggerMock,
                $this->_serializerMock
            ])
            ->onlyMethods(['getUrl'])
            ->getMock();

        $this
            ->_controller
            ->method('getUrl')
            ->with('sales/order/view', ['order_id' => $orderId])
            ->willReturn($redirectUrl);

        // JSON response should be set with redirect URL
        $this
            ->_resultJsonMock
            ->expects($this->once())
            ->method('setData')
            ->with(['redirectUrl' => $redirectUrl])
            ->willReturnSelf();

        $result = $this->_controller->execute();
        $this->assertInstanceOf(Json::class, $result);
    }

    /**
     * Test execution with cancellation service error
     *
     * @return void
     */
    public function testExecuteWithCancellationServiceError(): void
    {
        $paymentId = 'pay_123456';
        $cancellationReason = 'REQUESTED_BY_CUSTOMER';
        $orderId = '1';
        $errorMessage = 'Payment cannot be canceled';
        $redirectUrl = 'https://example.com/admin/sales/order/view/order_id/' . $orderId;

        // Setup the request with all required parameters
        $this->_requestMock->method('getParams')->willReturn([
            'payment_id' => $paymentId,
            'cancellation_reason' => $cancellationReason,
            'order_id' => $orderId
        ]);

        // Setup logger to accept any array for debug context
        $this
            ->_loggerMock
            ->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['[AdminCancel] Parameters', $this->isType('array')],
                ['[AdminCancel] Processing payment cancellation', $this->isType('array')]
            );

        // Create a mock payment that behaves as both a Payment object and an array
        $mockPayment = new MockPayment([
            'error' => true,
            'errorMessage' => $errorMessage
        ]);

        // Mock cancel payment service to return our special Payment mock
        $this
            ->_cancelPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'payment_id' => $paymentId,
                'cancellation_reason' => $cancellationReason
            ])
            ->willReturn($mockPayment);

        // Mock order management - should NOT be called
        $this
            ->_orderManagementMock
            ->expects($this->never())
            ->method('cancel');

        // Error message should be added
        $this
            ->_messageManagerMock
            ->expects($this->once())
            ->method('addErrorMessage');

        // Set up URL helper method
        $this->_controller = $this
            ->getMockBuilder(Cancel::class)
            ->setConstructorArgs([
                $this->_contextMock,
                $this->_resultJsonFactoryMock,
                $this->_cancelPaymentServiceMock,
                $this->_orderManagementMock,
                $this->_loggerMock,
                $this->_serializerMock
            ])
            ->onlyMethods(['getUrl'])
            ->getMock();

        $this
            ->_controller
            ->method('getUrl')
            ->with('sales/order/view', ['order_id' => $orderId])
            ->willReturn($redirectUrl);

        // JSON response should be set with redirect URL
        $this
            ->_resultJsonMock
            ->expects($this->once())
            ->method('setData')
            ->with(['redirectUrl' => $redirectUrl])
            ->willReturnSelf();

        $result = $this->_controller->execute();
        $this->assertInstanceOf(Json::class, $result);
    }

    /**
     * Test execution with exception from order management
     *
     * @return void
     */
    public function testExecuteWithOrderManagementException(): void
    {
        $paymentId = 'pay_123456';
        $cancellationReason = 'REQUESTED_BY_CUSTOMER';
        $orderId = '1';
        $errorMessage = 'Cannot cancel order';
        $redirectUrl = 'https://example.com/admin/sales/order/view/order_id/' . $orderId;

        // Setup the request with all required parameters
        $this->_requestMock->method('getParams')->willReturn([
            'payment_id' => $paymentId,
            'cancellation_reason' => $cancellationReason,
            'order_id' => $orderId
        ]);

        // Setup logger to accept any array for debug context
        $this
            ->_loggerMock
            ->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['[AdminCancel] Parameters', $this->isType('array')],
                ['[AdminCancel] Processing payment cancellation', $this->isType('array')]
            );

        // Also expect critical log with array context for error
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('critical')
            ->with($this->stringContains('Order cancellation failed'), $this->isType('array'));

        // Create payment mock
        $paymentMock = $this->createMock(Payment::class);

        // Mock cancel payment service
        $this
            ->_cancelPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with([
                'payment_id' => $paymentId,
                'cancellation_reason' => $cancellationReason
            ])
            ->willReturn($paymentMock);

        // Mock order management - throws exception
        $this
            ->_orderManagementMock
            ->expects($this->once())
            ->method('cancel')
            ->with($orderId)
            ->willThrowException(new \Exception($errorMessage));

        // Error message should be added
        $this
            ->_messageManagerMock
            ->expects($this->once())
            ->method('addErrorMessage');

        // Set up URL helper method
        $this->_controller = $this
            ->getMockBuilder(Cancel::class)
            ->setConstructorArgs([
                $this->_contextMock,
                $this->_resultJsonFactoryMock,
                $this->_cancelPaymentServiceMock,
                $this->_orderManagementMock,
                $this->_loggerMock,
                $this->_serializerMock
            ])
            ->onlyMethods(['getUrl'])
            ->getMock();

        $this
            ->_controller
            ->method('getUrl')
            ->with('sales/order/view', ['order_id' => $orderId])
            ->willReturn($redirectUrl);

        // JSON response should be set with redirect URL
        $this
            ->_resultJsonMock
            ->expects($this->once())
            ->method('setData')
            ->with(['redirectUrl' => $redirectUrl])
            ->willReturnSelf();

        $result = $this->_controller->execute();
        $this->assertInstanceOf(Json::class, $result);
    }
}

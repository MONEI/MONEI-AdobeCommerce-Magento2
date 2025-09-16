<?php

/**
 * Test case for Admin Creditmemo Save Controller.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Adminhtml\Order\Creditmemo;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\ObjectManager\ObjectManager;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Sales\Model\Order\Creditmemo;
use Monei\MoneiPayment\Controller\Adminhtml\Order\Creditmemo\Save;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test case for Admin Creditmemo Save Controller.
 */
class SaveTest extends TestCase
{
    /**
     * Test execute with valid creditmemo
     *
     * @return void
     */
    public function testExecuteWithValidCreditmemo(): void
    {
        $orderId = '100000001';
        $creditmemoId = '1000';
        $creditmemoData = [
            'comment_text' => 'Test comment',
            'refund_reason' => 'customer_dissatisfied'
        ];

        // Set up request mock
        $requestMock = $this
            ->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParam'])
            ->addMethods(['getPost'])
            ->getMockForAbstractClass();
        $requestMock->method('getParam')->willReturnMap([
            ['order_id', null, $orderId],
            ['creditmemo_id', null, $creditmemoId],
            ['invoice_id', null, null],
            ['creditmemo', null, $creditmemoData]
        ]);
        $requestMock->method('getPost')->willReturn($creditmemoData);

        // Set up message manager
        $messageManagerMock = $this->createMock(ManagerInterface::class);
        $messageManagerMock->expects($this->once())->method('addSuccessMessage');

        // Set up redirect
        $redirectMock = $this->createMock(Redirect::class);
        $redirectMock->method('setPath')->willReturnSelf();

        $resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $resultRedirectFactoryMock->method('create')->willReturn($redirectMock);

        // Set up session mock
        $sessionMock = $this
            ->getMockBuilder(\Magento\Backend\Model\Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCommentText', 'getCommentText'])
            ->getMock();

        // Set up context
        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($requestMock);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);
        $contextMock->method('getResultRedirectFactory')->willReturn($resultRedirectFactoryMock);

        // Set up object manager
        $objectManagerMock = $this->createMock(ObjectManager::class);
        $contextMock->method('getObjectManager')->willReturn($objectManagerMock);

        // Set up order mock
        $orderMock = $this->createMock(\Magento\Sales\Model\Order::class);

        // Set up creditmemo mock
        $creditmemoMock = $this
            ->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isValidGrandTotal', 'addComment', 'getOrder', 'getOrderId', 'setData'])
            ->addMethods(['setCustomerNote', 'setCustomerNoteNotify'])
            ->getMock();
        $creditmemoMock->method('isValidGrandTotal')->willReturn(true);
        $creditmemoMock->method('getOrder')->willReturn($orderMock);
        $creditmemoMock->method('getOrderId')->willReturn($orderId);

        // Set up creditmemo loader
        $creditmemoLoaderMock = $this
            ->getMockBuilder(CreditmemoLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $creditmemoLoaderMock->method('load')->willReturn($creditmemoMock);

        // Set up creditmemo management
        $creditmemoManagementMock = $this->createMock(CreditmemoManagementInterface::class);
        $objectManagerMock
            ->method('create')
            ->with(CreditmemoManagementInterface::class)
            ->willReturn($creditmemoManagementMock);

        // Set up creditmemo sender
        $creditmemoSenderMock = $this->createMock(CreditmemoSender::class);

        // Set up sales data helper
        $salesDataMock = $this->createMock(SalesData::class);

        // Create controller instance
        $controller = $this
            ->getMockBuilder(Save::class)
            ->onlyMethods(['_getSession'])
            ->setConstructorArgs([
                $contextMock,
                $creditmemoLoaderMock,
                $creditmemoSenderMock,
                $salesDataMock
            ])
            ->getMock();

        // Set up the _getSession method to return our session mock
        $controller->method('_getSession')->willReturn($sessionMock);

        // Execute the controller
        $result = $controller->execute();

        // Assert that a redirect is returned
        $this->assertInstanceOf(Redirect::class, $result);
    }

    /**
     * Test execute with invalid grand total
     *
     * @return void
     */
    public function testExecuteWithInvalidGrandTotal(): void
    {
        $orderId = '100000001';
        $creditmemoData = [
            'comment_text' => 'Test comment'
        ];

        // Set up request mock
        $requestMock = $this
            ->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParam'])
            ->addMethods(['getPost'])
            ->getMockForAbstractClass();
        $requestMock->method('getParam')->willReturnMap([
            ['order_id', null, $orderId],
            ['creditmemo_id', null, null],
            ['invoice_id', null, null],
            ['creditmemo', null, $creditmemoData]
        ]);
        $requestMock->method('getPost')->willReturn($creditmemoData);

        // Set up message manager - expect an error message
        $messageManagerMock = $this->createMock(ManagerInterface::class);
        $messageManagerMock->expects($this->once())->method('addErrorMessage');

        // Set up redirect
        $redirectMock = $this->createMock(Redirect::class);
        $redirectMock->method('setPath')->willReturnSelf();

        $resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $resultRedirectFactoryMock->method('create')->willReturn($redirectMock);

        // Set up session mock
        $sessionMock = $this
            ->getMockBuilder(\Magento\Backend\Model\Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCommentText', 'getCommentText', 'setFormData'])
            ->getMock();

        // Set up context
        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($requestMock);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);
        $contextMock->method('getResultRedirectFactory')->willReturn($resultRedirectFactoryMock);

        // Set up object manager
        $objectManagerMock = $this->createMock(ObjectManager::class);
        $contextMock->method('getObjectManager')->willReturn($objectManagerMock);

        // Set up creditmemo mock with invalid grand total
        $creditmemoMock = $this
            ->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isValidGrandTotal'])
            ->getMock();
        $creditmemoMock->method('isValidGrandTotal')->willReturn(false);

        // Set up creditmemo loader
        $creditmemoLoaderMock = $this
            ->getMockBuilder(CreditmemoLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $creditmemoLoaderMock->method('load')->willReturn($creditmemoMock);

        // Set up creditmemo sender
        $creditmemoSenderMock = $this->createMock(CreditmemoSender::class);

        // Set up sales data helper
        $salesDataMock = $this->createMock(SalesData::class);

        // Create controller instance
        $controller = $this
            ->getMockBuilder(Save::class)
            ->onlyMethods(['_getSession'])
            ->setConstructorArgs([
                $contextMock,
                $creditmemoLoaderMock,
                $creditmemoSenderMock,
                $salesDataMock
            ])
            ->getMock();

        // Set up the _getSession method to return our session mock
        $controller->method('_getSession')->willReturn($sessionMock);

        // Execute the controller
        $result = $controller->execute();

        // Assert that a redirect is returned
        $this->assertInstanceOf(Redirect::class, $result);
    }

    /**
     * Test execute with exception
     *
     * @return void
     */
    public function testExecuteWithException(): void
    {
        $orderId = '100000001';
        $errorMessage = 'Credit memo cannot be created';
        $creditmemoData = [
            'comment_text' => 'Test comment'
        ];

        // Set up request mock
        $requestMock = $this
            ->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParam'])
            ->addMethods(['getPost'])
            ->getMockForAbstractClass();
        $requestMock->method('getParam')->willReturnMap([
            ['order_id', null, $orderId],
            ['creditmemo_id', null, null],
            ['invoice_id', null, null],
            ['creditmemo', null, $creditmemoData]
        ]);
        $requestMock->method('getPost')->willReturn($creditmemoData);

        // Set up message manager - expect an error message
        $messageManagerMock = $this->createMock(ManagerInterface::class);
        $messageManagerMock->expects($this->once())->method('addErrorMessage');

        // Set up redirect
        $redirectMock = $this->createMock(Redirect::class);
        $redirectMock->method('setPath')->willReturnSelf();

        $resultRedirectFactoryMock = $this->createMock(RedirectFactory::class);
        $resultRedirectFactoryMock->method('create')->willReturn($redirectMock);

        // Set up session mock
        $sessionMock = $this
            ->getMockBuilder(\Magento\Backend\Model\Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCommentText', 'getCommentText', 'setFormData'])
            ->getMock();

        // Set up context
        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($requestMock);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);
        $contextMock->method('getResultRedirectFactory')->willReturn($resultRedirectFactoryMock);

        // Set up object manager
        $objectManagerMock = $this->createMock(ObjectManager::class);
        $contextMock->method('getObjectManager')->willReturn($objectManagerMock);

        // Set up order mock
        $orderMock = $this->createMock(\Magento\Sales\Model\Order::class);

        // Set up creditmemo mock
        $creditmemoMock = $this
            ->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isValidGrandTotal', 'addComment', 'getOrder'])
            ->addMethods(['setCustomerNote', 'setCustomerNoteNotify'])
            ->getMock();
        $creditmemoMock->method('isValidGrandTotal')->willReturn(true);
        $creditmemoMock->method('getOrder')->willReturn($orderMock);

        // Set up creditmemo loader
        $creditmemoLoaderMock = $this
            ->getMockBuilder(CreditmemoLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $creditmemoLoaderMock->method('load')->willReturn($creditmemoMock);

        // Set up creditmemo management that throws exception
        $creditmemoManagementMock = $this->createMock(CreditmemoManagementInterface::class);
        $creditmemoManagementMock
            ->method('refund')
            ->willThrowException(new LocalizedException(__($errorMessage)));
        $objectManagerMock
            ->method('create')
            ->with(CreditmemoManagementInterface::class)
            ->willReturn($creditmemoManagementMock);

        // Set up creditmemo sender
        $creditmemoSenderMock = $this->createMock(CreditmemoSender::class);

        // Set up sales data helper
        $salesDataMock = $this->createMock(SalesData::class);

        // Create controller instance
        $controller = $this
            ->getMockBuilder(Save::class)
            ->onlyMethods(['_getSession'])
            ->setConstructorArgs([
                $contextMock,
                $creditmemoLoaderMock,
                $creditmemoSenderMock,
                $salesDataMock
            ])
            ->getMock();

        // Set up the _getSession method to return our session mock
        $controller->method('_getSession')->willReturn($sessionMock);

        // Execute the controller
        $result = $controller->execute();

        // Assert that a redirect is returned
        $this->assertInstanceOf(Redirect::class, $result);
    }
}

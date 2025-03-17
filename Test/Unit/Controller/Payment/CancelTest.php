<?php

/**
 * Test for Cancel controller
 *
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Controller\Payment\Cancel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for Cancel controller
 */
class CancelTest extends TestCase
{
    /**
     * Cancel controller instance
     *
     * @var Cancel
     */
    private $_cancelController;

    /**
     * Checkout session mock
     *
     * @var MockObject|Session
     */
    private $_checkoutSessionMock;

    /**
     * Order repository mock
     *
     * @var MockObject|OrderRepositoryInterface
     */
    private $_orderRepositoryMock;

    /**
     * Message manager mock
     *
     * @var MockObject|ManagerInterface
     */
    private $_messageManagerMock;

    /**
     * Result redirect mock
     *
     * @var MockObject|MagentoRedirect
     */
    private $_resultRedirectMock;

    /**
     * Request mock
     *
     * @var MockObject|HttpRequest
     */
    private $_requestMock;

    /**
     * Order mock
     *
     * @var MockObject|Order
     */
    private $_orderMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_resultRedirectMock = $this->createMock(MagentoRedirect::class);
        $this->_requestMock = $this->createMock(HttpRequest::class);
        $this->_messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->_checkoutSessionMock = $this->createMock(Session::class);
        $this->_orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->_orderMock = $this->createMock(Order::class);

        $this->_cancelController = new Cancel(
            $this->_checkoutSessionMock,
            $this->_orderRepositoryMock,
            $this->_messageManagerMock,
            $this->_resultRedirectMock,
            $this->_requestMock
        );
    }

    /**
     * Test execute method
     *
     * @return void
     */
    public function testExecute(): void
    {
        // Setup last order
        $this
            ->_checkoutSessionMock
            ->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->_orderMock);

        // Order should be canceled
        $this
            ->_orderMock
            ->expects($this->once())
            ->method('cancel');

        // Order should be saved
        $this
            ->_orderRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->_orderMock);

        // Quote should be restored
        $this
            ->_checkoutSessionMock
            ->expects($this->once())
            ->method('restoreQuote');

        // Setup message manager expectations
        $this
            ->_messageManagerMock
            ->expects($this->once())
            ->method('addNoticeMessage')
            ->with($this->anything());

        // Setup redirect expectations
        $this
            ->_resultRedirectMock
            ->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $result = $this->_cancelController->execute();

        $this->assertSame($this->_resultRedirectMock, $result);
    }
}

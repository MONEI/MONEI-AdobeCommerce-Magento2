<?php

/**
 * Test for Redirect controller
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
use Magento\Framework\Controller\Result\Redirect as MagentoRedirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order;
use Monei\Model\Payment as MoneiPayment;
use Monei\Model\PaymentNextAction;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Controller\Payment\Redirect as RedirectController;
use Monei\MoneiPayment\Service\Shared\PaymentMethodCodeMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for Redirect controller
 *
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 * @link     https://docs.monei.com/api
 */
class RedirectTest extends TestCase
{
    /**
     * Redirect controller instance
     *
     * @var RedirectController
     */
    private $_controller;

    /**
     * Checkout session mock
     *
     * @var Session|MockObject
     */
    private $_checkoutSessionMock;

    /**
     * Create payment action mock
     *
     * @var CreatePaymentInterface|MockObject
     */
    private $_createPaymentMock;

    /**
     * Result redirect mock
     *
     * @var MagentoRedirect|MockObject
     */
    private $_resultRedirectMock;

    /**
     * Payment method code mapper mock
     *
     * @var PaymentMethodCodeMapper|MockObject
     */
    private $_paymentMethodCodeMapperMock;

    /**
     * Order mock
     *
     * @var Order|MockObject
     */
    private $_orderMock;

    /**
     * Address mock
     *
     * @var Address|MockObject
     */
    private $_addressMock;

    /**
     * Payment mock
     *
     * @var Payment|MockObject
     */
    private $_paymentMock;

    /**
     * Payment method mock
     *
     * @var MethodInterface|MockObject
     */
    private $_paymentMethodMock;

    /**
     * Monei payment mock
     *
     * @var MoneiPayment|MockObject
     */
    private $_moneiPaymentMock;

    /**
     * Payment next action mock
     *
     * @var PaymentNextAction|MockObject
     */
    private $_nextActionMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_checkoutSessionMock = $this->createMock(Session::class);
        $this->_createPaymentMock = $this->createMock(CreatePaymentInterface::class);
        $this->_resultRedirectMock = $this->createMock(MagentoRedirect::class);
        $this->_paymentMethodCodeMapperMock = $this->createMock(PaymentMethodCodeMapper::class);

        $this->_orderMock = $this->createMock(Order::class);
        $this->_addressMock = $this->createMock(Address::class);
        $this->_paymentMock = $this->createMock(Payment::class);
        $this->_paymentMethodMock = $this->createMock(MethodInterface::class);
        $this->_moneiPaymentMock = $this->createMock(MoneiPayment::class);
        $this->_nextActionMock = $this->createMock(PaymentNextAction::class);

        // Configure session mock
        $this->_checkoutSessionMock->method('getLastRealOrder')->willReturn($this->_orderMock);

        // Configure order mock
        $this->_orderMock->method('getPayment')->willReturn($this->_paymentMock);
        $this->_orderMock->method('getId')->willReturn(123);
        $this->_orderMock->method('getEntityId')->willReturn(123);
        $this->_orderMock->method('getIncrementId')->willReturn('100000123');
        $this->_orderMock->method('getBaseGrandTotal')->willReturn(100.0);
        $this->_orderMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $this->_orderMock->method('getBillingAddress')->willReturn($this->_addressMock);

        // Configure address mock
        $this->_addressMock->method('getFirstname')->willReturn('John');
        $this->_addressMock->method('getLastname')->willReturn('Doe');
        $this->_addressMock->method('getEmail')->willReturn('test@example.com');
        $this->_addressMock->method('getTelephone')->willReturn('123456789');
        $this->_addressMock->method('getStreet')->willReturn(['123 Main St']);
        $this->_addressMock->method('getCity')->willReturn('New York');
        $this->_addressMock->method('getCountryId')->willReturn('US');
        $this->_addressMock->method('getPostcode')->willReturn('10001');
        $this->_addressMock->method('getRegion')->willReturn('NY');

        // Configure payment mock
        $this->_paymentMock->method('getMethodInstance')->willReturn($this->_paymentMethodMock);

        $this->_controller = new RedirectController(
            $this->_checkoutSessionMock,
            $this->_createPaymentMock,
            $this->_resultRedirectMock,
            $this->_paymentMethodCodeMapperMock
        );
    }

    /**
     * Test execute method with redirect URL
     *
     * @return void
     */
    public function testExecuteWithRedirectUrl(): void
    {
        // Payment method configuration
        $this->_paymentMethodMock->method('getCode')->willReturn('monei');

        // Payment method mapper
        $this->_paymentMethodCodeMapperMock->method('execute')->willReturn(['card']);

        // Set up next action with redirect URL
        $this->_nextActionMock->method('getRedirectUrl')->willReturn('https://monei.com/pay/123');

        // Configure Monei payment mock
        $this->_moneiPaymentMock->method('getNextAction')->willReturn($this->_nextActionMock);

        // Create payment action returns Monei payment
        $this->_createPaymentMock->method('execute')->willReturn($this->_moneiPaymentMock);

        // Configure result redirect
        $this->_resultRedirectMock->method('setUrl')->willReturnSelf();

        // Execute the controller
        $result = $this->_controller->execute();

        // Assert redirect is returned
        $this->assertSame($this->_resultRedirectMock, $result);
    }

    /**
     * Test execute method without redirect URL
     *
     * @return void
     */
    public function testExecuteWithoutRedirectUrl(): void
    {
        // Payment method configuration
        $this->_paymentMethodMock->method('getCode')->willReturn('monei');

        // Payment method mapper
        $this->_paymentMethodCodeMapperMock->method('execute')->willReturn(['card']);

        // Configure Monei payment mock with no next action
        $this->_moneiPaymentMock->method('getNextAction')->willReturn(null);

        // Create payment action returns Monei payment
        $this->_createPaymentMock->method('execute')->willReturn($this->_moneiPaymentMock);

        // Configure result redirect
        $this->_resultRedirectMock->method('setPath')->willReturnSelf();

        // Execute the controller
        $result = $this->_controller->execute();

        // Assert redirect is returned
        $this->assertSame($this->_resultRedirectMock, $result);
    }

    /**
     * Test execute method with exception
     *
     * @return void
     */
    public function testExecuteWithException(): void
    {
        // Payment method configuration
        $this->_paymentMethodMock->method('getCode')->willReturn('monei');

        // Payment method mapper
        $this->_paymentMethodCodeMapperMock->method('execute')->willReturn(['card']);

        // Create payment action throws exception
        $this
            ->_createPaymentMock
            ->method('execute')
            ->willThrowException(new LocalizedException(__('Payment error')));

        // Expect the exception to be thrown
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Payment error');

        // Execute the controller
        $this->_controller->execute();
    }
}

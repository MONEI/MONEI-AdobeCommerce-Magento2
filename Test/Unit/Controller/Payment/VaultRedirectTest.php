<?php

/**
 * Test for VaultRedirect controller
 *
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Monei\MoneiPayment\Api\Service\Checkout\CreateLoggedMoneiPaymentVaultInterface;
use Monei\MoneiPayment\Controller\Payment\VaultRedirect;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for VaultRedirect controller
 */
class VaultRedirectTest extends TestCase
{
    /**
     * VaultRedirect controller instance
     *
     * @var VaultRedirect
     */
    private $_vaultRedirectController;

    /**
     * Customer session mock
     *
     * @var MockObject|CustomerSession
     */
    private $_customerSessionMock;

    /**
     * Checkout session mock
     *
     * @var MockObject|CheckoutSession
     */
    private $_checkoutSessionMock;

    /**
     * Create logged Monei payment vault mock
     *
     * @var MockObject|CreateLoggedMoneiPaymentVaultInterface
     */
    private $_createLoggedMoneiPaymentVaultMock;

    /**
     * Form key validator mock
     *
     * @var MockObject|FormKeyValidator
     */
    private $_formKeyValidatorMock;

    /**
     * Logger mock
     *
     * @var MockObject|LoggerInterface
     */
    private $_loggerMock;

    /**
     * Redirect mock
     *
     * @var MockObject|Redirect
     */
    private $_redirectMock;

    /**
     * Message manager mock
     *
     * @var MockObject|ManagerInterface
     */
    private $_messageManagerMock;

    /**
     * Request mock
     *
     * @var MockObject|RequestInterface
     */
    private $_requestMock;

    /**
     * Order mock
     *
     * @var MockObject|OrderInterface
     */
    private $_orderMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_customerSessionMock = $this->createMock(CustomerSession::class);
        $this->_checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->_createLoggedMoneiPaymentVaultMock = $this->createMock(CreateLoggedMoneiPaymentVaultInterface::class);
        $this->_formKeyValidatorMock = $this->createMock(FormKeyValidator::class);
        $this->_loggerMock = $this->createMock(LoggerInterface::class);
        $this->_redirectMock = $this->createMock(Redirect::class);
        $this->_messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->_requestMock = $this->createMock(RequestInterface::class);
        $this->_orderMock = $this->createMock(OrderInterface::class);

        $this->_vaultRedirectController = new VaultRedirect(
            $this->_customerSessionMock,
            $this->_checkoutSessionMock,
            $this->_createLoggedMoneiPaymentVaultMock,
            $this->_formKeyValidatorMock,
            $this->_loggerMock,
            $this->_redirectMock,
            $this->_messageManagerMock,
            $this->_requestMock
        );
    }

    /**
     * Test execute method with successful payment processing
     *
     * @return void
     */
    public function testExecuteSuccessful(): void
    {
        $publicHash = 'test_public_hash';
        $quoteId = '123';
        $entityId = 456;
        $paymentId = 'pay_abc123';
        $redirectUrl = 'https://monei.com/payment/12345';
        $orderId = '000000123';

        // Setup request params
        $this
            ->_requestMock
            ->expects($this->once())
            ->method('getParam')
            ->with('public_hash')
            ->willReturn($publicHash);

        // Setup form key validation
        $this
            ->_formKeyValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with($this->_requestMock)
            ->willReturn(true);

        // Setup last real order
        $this
            ->_checkoutSessionMock
            ->expects($this->atLeastOnce())
            ->method('getLastRealOrder')
            ->willReturn($this->_orderMock);

        $this
            ->_orderMock
            ->expects($this->once())
            ->method('getEntityId')
            ->willReturn($entityId);

        $this
            ->_orderMock
            ->expects($this->once())
            ->method('getQuoteId')
            ->willReturn($quoteId);

        $this
            ->_orderMock
            ->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderId);

        // Setup payment processor expectations
        $this
            ->_createLoggedMoneiPaymentVaultMock
            ->expects($this->once())
            ->method('execute')
            ->with($quoteId, $publicHash)
            ->willReturn([
                'success' => true,
                'payment_id' => $paymentId,
                'redirect_url' => $redirectUrl
            ]);

        // Setup session to store payment ID using magic method
        $this
            ->_checkoutSessionMock
            ->expects($this->once())
            ->method('__call')
            ->with('setLastMoneiPaymentId', [$paymentId]);

        // Setup logger for info
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('[Vault] Payment created successfully', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
            ]);

        // Setup redirect expectations
        $this
            ->_redirectMock
            ->expects($this->once())
            ->method('setUrl')
            ->with($redirectUrl)
            ->willReturnSelf();

        $result = $this->_vaultRedirectController->execute();

        $this->assertSame($this->_redirectMock, $result);
    }

    /**
     * Test execute method with error during payment processing
     *
     * @return void
     */
    public function testExecuteWithError(): void
    {
        // Setup request params
        $this
            ->_requestMock
            ->expects($this->once())
            ->method('getParam')
            ->with('public_hash')
            ->willReturn('test_public_hash');

        // Setup form key validation
        $this
            ->_formKeyValidatorMock
            ->expects($this->once())
            ->method('validate')
            ->with($this->_requestMock)
            ->willReturn(true);

        // Setup last real order to return null
        $this
            ->_checkoutSessionMock
            ->expects($this->atLeastOnce())
            ->method('getLastRealOrder')
            ->willReturn(null);

        // Setup message manager for error
        $this
            ->_messageManagerMock
            ->expects($this->once())
            ->method('addErrorMessage')
            ->with('No order found. Please return to checkout and try again.');

        // Setup logger for critical error
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('critical')
            ->with(
                '[Vault] Redirect error: No order found. Please return to checkout and try again.',
                $this->anything()
            );

        // Setup logger for info about quote restoration
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('[Vault] Restored quote');

        // Setup session restore quote
        $this
            ->_checkoutSessionMock
            ->expects($this->once())
            ->method('restoreQuote');

        // Setup redirect expectations
        $this
            ->_redirectMock
            ->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart', [])
            ->willReturnSelf();

        $result = $this->_vaultRedirectController->execute();

        $this->assertSame($this->_redirectMock, $result);
    }
}

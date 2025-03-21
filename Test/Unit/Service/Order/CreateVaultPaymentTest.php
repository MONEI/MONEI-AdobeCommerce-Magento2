<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Order;

use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CreateVaultPayment service
 */
class CreateVaultPaymentTest extends TestCase
{
    /**
     * @var PaymentTokenFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentTokenFactoryMock;

    /**
     * @var GetPaymentInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $getPaymentServiceMock;

    /**
     * @var Logger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    /**
     * @var CreateVaultPayment
     */
    private $createVaultPayment;

    protected function setUp(): void
    {
        $this->paymentTokenFactoryMock = $this->createMock(PaymentTokenFactoryInterface::class);
        $this->getPaymentServiceMock = $this->createMock(GetPaymentInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->createVaultPayment = new CreateVaultPayment(
            $this->paymentTokenFactoryMock,
            $this->getPaymentServiceMock,
            $this->loggerMock
        );
    }

    /**
     * Test successful tokenization for a card payment
     */
    public function testExecuteWithCardPaymentSuccess(): void
    {
        $moneiPaymentId = 'pay_123456789';
        $gatewayToken = 'token_123456789';
        $expirationTimestamp = strtotime('+1 year');
        $formattedExpirationDate = date('m/Y', $expirationTimestamp);

        // Create order payment mock with extension attributes and method
        $orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentMock->method('getMethod')->willReturn(Monei::CARD_CODE);

        // Create extension attributes mock
        $extensionAttributesMock = $this->createMock(OrderPaymentExtensionInterface::class);
        $orderPaymentMock->method('getExtensionAttributes')->willReturn($extensionAttributesMock);

        // Check for method existence and setup expectations for extension attributes
        $reflectionClass = new \ReflectionClass(OrderPaymentExtensionInterface::class);
        if ($reflectionClass->hasMethod('setVaultPaymentToken')) {
            $extensionAttributesMock
                ->expects($this->once())
                ->method('setVaultPaymentToken')
                ->with($this->isInstanceOf(PaymentTokenInterface::class));
        }

        // Create payment token mock
        $paymentTokenMock = $this->createMock(PaymentTokenInterface::class);
        $this->paymentTokenFactoryMock->method('create')->willReturn($paymentTokenMock);

        // Set expectations for payment token
        $paymentTokenMock->expects($this->once())->method('setGatewayToken')->with($gatewayToken);
        $paymentTokenMock->expects($this->once())->method('setType')->with(Monei::VAULT_TYPE);
        $paymentTokenMock->expects($this->once())->method('setExpiresAt');
        $paymentTokenMock->expects($this->once())->method('setTokenDetails');

        // Create card mock
        $cardMock = $this
            ->getMockBuilder(\stdClass::class)
            ->addMethods(['getExpiration', 'getType', 'getBrand', 'getCardholderName', 'getLast4'])
            ->getMock();
        $cardMock->method('getExpiration')->willReturn($expirationTimestamp);
        $cardMock->method('getType')->willReturn('credit');
        $cardMock->method('getBrand')->willReturn('visa');
        $cardMock->method('getCardholderName')->willReturn('John Doe');
        $cardMock->method('getLast4')->willReturn('4242');

        // Create payment method mock
        $paymentMethodMock = $this
            ->getMockBuilder(\stdClass::class)
            ->addMethods(['getCard'])
            ->getMock();
        $paymentMethodMock->method('getCard')->willReturn($cardMock);

        // Create Monei Payment mock
        $moneiPaymentMock = $this
            ->getMockBuilder(\Monei\Model\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStatus', 'getPaymentToken', 'getPaymentMethod'])
            ->getMock();
        $moneiPaymentMock->method('getId')->willReturn($moneiPaymentId);
        $moneiPaymentMock->method('getStatus')->willReturn('SUCCEEDED');
        $moneiPaymentMock->method('getPaymentToken')->willReturn($gatewayToken);
        $moneiPaymentMock->method('getPaymentMethod')->willReturn($paymentMethodMock);

        // Configure get payment service to return our payment
        $this
            ->getPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with($moneiPaymentId)
            ->willReturn($moneiPaymentMock);

        // Set logging expectations - allow any number of debug calls
        $this->loggerMock->method('debug');

        // Call the execute method
        $result = $this->createVaultPayment->execute($moneiPaymentId, $orderPaymentMock);

        // Assert that result is true (tokenization successful)
        $this->assertTrue($result);
    }

    /**
     * Test when payment method is not a card (should skip tokenization)
     */
    public function testExecuteWithNonCardPayment(): void
    {
        $moneiPaymentId = 'pay_123456789';

        // Create order payment mock
        $orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentMock->method('getMethod')->willReturn('monei_paypal');  // Not a card payment

        // Set logging expectations - allow any number of debug calls
        $this->loggerMock->method('debug');

        // Call the execute method
        $result = $this->createVaultPayment->execute($moneiPaymentId, $orderPaymentMock);

        // Assert that result is false (tokenization skipped)
        $this->assertFalse($result);
    }

    /**
     * Test when payment token is missing
     */
    public function testExecuteWithMissingToken(): void
    {
        $moneiPaymentId = 'pay_123456789';

        // Create order payment mock
        $orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentMock->method('getMethod')->willReturn(Monei::CARD_CODE);

        // Create Monei Payment mock with missing token
        $moneiPaymentMock = $this
            ->getMockBuilder(\Monei\Model\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStatus', 'getPaymentToken', 'getPaymentMethod'])
            ->getMock();
        $moneiPaymentMock->method('getId')->willReturn($moneiPaymentId);
        $moneiPaymentMock->method('getStatus')->willReturn('SUCCEEDED');
        $moneiPaymentMock->method('getPaymentToken')->willReturn('');  // Empty token

        // Configure get payment service
        $this
            ->getPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with($moneiPaymentId)
            ->willReturn($moneiPaymentMock);

        // Set logging expectations - allow any number of debug calls
        $this->loggerMock->method('debug');

        // Call the execute method
        $result = $this->createVaultPayment->execute($moneiPaymentId, $orderPaymentMock);

        // Assert that result is false (tokenization failed)
        $this->assertFalse($result);
    }

    /**
     * Test exception handling
     */
    public function testExecuteWithException(): void
    {
        $moneiPaymentId = 'pay_123456789';

        // Create order payment mock
        $orderPaymentMock = $this->createMock(OrderPaymentInterface::class);
        $orderPaymentMock->method('getMethod')->willReturn(Monei::CARD_CODE);

        // Configure get payment service to throw exception
        $this
            ->getPaymentServiceMock
            ->expects($this->once())
            ->method('execute')
            ->with($moneiPaymentId)
            ->willThrowException(new \Exception('API Error'));

        // Set logging expectations - allow any number of debug calls
        $this->loggerMock->method('debug');

        // Call the execute method
        $result = $this->createVaultPayment->execute($moneiPaymentId, $orderPaymentMock);

        // Assert that result is false (tokenization failed)
        $this->assertFalse($result);
    }
}

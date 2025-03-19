<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Helper;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Helper\PaymentMethod;
use Monei\MoneiPayment\Helper\PaymentMethodFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for PaymentMethodFormatter
 */
class PaymentMethodFormatterTest extends TestCase
{
    /**
     * PaymentMethodFormatter instance
     *
     * @var PaymentMethodFormatter
     */
    private $paymentMethodFormatter;

    /**
     * PaymentMethod mock
     *
     * @var PaymentMethod|MockObject
     */
    private $paymentMethodHelperMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->paymentMethodHelperMock = $this->createMock(PaymentMethod::class);
        $this->paymentMethodFormatter = new PaymentMethodFormatter($this->paymentMethodHelperMock);
    }

    /**
     * Test formatPaymentMethodDisplay with card information
     *
     * @return void
     */
    public function testFormatPaymentMethodDisplayWithCardInfo(): void
    {
        // Set up test data
        $paymentInfo = [
            'brand' => 'VISA',
            'type' => 'credit',
            'last4' => '1234'
        ];

        // Set up mocks
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodName')
            ->with('visa')
            ->willReturn('Visa');

        // Execute test
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify result
        $this->assertEquals('Visa Credit •••• 1234', $result);
    }

    /**
     * Test formatPaymentMethodDisplay with Bizum information
     *
     * @return void
     */
    public function testFormatPaymentMethodDisplayWithBizum(): void
    {
        // Set up test data
        $paymentInfo = [
            'method' => PaymentMethods::PAYMENT_METHODS_BIZUM,
            'phoneNumber' => '666777888'
        ];

        // Set up mocks
        $methodDetails = ['name' => 'Bizum'];
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodByMoneiCode')
            ->with(PaymentMethods::PAYMENT_METHODS_BIZUM)
            ->willReturn($methodDetails);

        // Execute test
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify result
        $this->assertEquals('Bizum •••••888', $result);
    }

    /**
     * Test formatPaymentMethodDisplay with PayPal information
     *
     * @return void
     */
    public function testFormatPaymentMethodDisplayWithPayPal(): void
    {
        // Set up test data
        $paymentInfo = [
            'method' => PaymentMethods::PAYMENT_METHODS_PAYPAL,
            'orderId' => 'PAY-123456',
            'payerId' => 'CUST-789',
            'email' => 'customer@example.com'
        ];

        // Set up mocks
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodByMoneiCode')
            ->with(PaymentMethods::PAYMENT_METHODS_PAYPAL)
            ->willReturn(null);

        // Execute test
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify result - contains all the PayPal info in parentheses
        $this->assertStringContainsString('PayPal', $result);
        $this->assertStringContainsString('PAY-123456', $result);
        $this->assertStringContainsString('CUST-789', $result);
        $this->assertStringContainsString('customer@example.com', $result);
    }

    /**
     * Test formatPaymentMethodDisplay with unknown method
     *
     * @return void
     */
    public function testFormatPaymentMethodDisplayWithUnknownMethod(): void
    {
        // Set up test data
        $paymentInfo = [
            'method' => 'unknownMethod'
        ];

        // Set up mocks
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodByMoneiCode')
            ->with('unknownMethod')
            ->willReturn(null);

        // Execute test
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify result - should convert camelCase to Title Case
        $this->assertEquals('Unknown Method', $result);
    }

    /**
     * Test formatPhoneNumber with various formats
     *
     * @param string $input Raw phone number
     * @param string $expected Formatted phone number
     * @return void
     * @dataProvider phoneNumberDataProvider
     */
    public function testFormatPhoneNumber(string $input, string $expected): void
    {
        $result = $this->paymentMethodFormatter->formatPhoneNumber($input);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for testFormatPhoneNumber
     *
     * @return array
     */
    public function phoneNumberDataProvider(): array
    {
        return [
            'international' => ['+34666777888', '+34 666 777 888'],
            'national' => ['666777888', '666 777 888'],
            'with spaces' => ['+34 666 777 888', '+34 666 777 888'],
            'with dashes' => ['+34-666-777-888', '+34 666 777 888'],
            'short number' => ['12345', '12345']
        ];
    }

    /**
     * Test formatWalletDisplay
     *
     * @return void
     */
    public function testFormatWalletDisplay(): void
    {
        // With known wallet
        $this
            ->paymentMethodHelperMock
            ->expects($this->exactly(2))
            ->method('getPaymentMethodByMoneiCode')
            ->withConsecutive(
                ['applePay'],
                ['newWallet']
            )
            ->willReturnOnConsecutiveCalls(
                ['name' => 'Apple Pay'],
                null
            );

        $result = $this->paymentMethodFormatter->formatWalletDisplay('applePay');
        $this->assertEquals('Apple Pay', $result);

        // With unknown wallet - should format camelCase
        $result = $this->paymentMethodFormatter->formatWalletDisplay('newWallet');
        $this->assertEquals('New Wallet', $result);
    }

    /**
     * Test getPaymentMethodIcon
     *
     * @return void
     */
    public function testGetPaymentMethodIcon(): void
    {
        // With method type
        $paymentInfo = ['method' => 'applePay'];
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getIconFromPaymentType')
            ->with('applePay', '')
            ->willReturn('icon-url.png');

        $result = $this->paymentMethodFormatter->getPaymentMethodIcon($paymentInfo);
        $this->assertEquals('icon-url.png', $result);

        // With card brand only
        $paymentInfo = ['brand' => 'visa'];
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getCardIcon')
            ->with('visa')
            ->willReturn('visa-icon.png');

        $result = $this->paymentMethodFormatter->getPaymentMethodIcon($paymentInfo);
        $this->assertEquals('visa-icon.png', $result);

        // With no valid info
        $paymentInfo = [];
        $result = $this->paymentMethodFormatter->getPaymentMethodIcon($paymentInfo);
        $this->assertNull($result);
    }

    /**
     * Test getPaymentMethodDimensions
     *
     * @return void
     */
    public function testGetPaymentMethodDimensions(): void
    {
        // With method type and details
        $paymentInfo = ['method' => 'applePay'];
        $methodDetails = [
            'width' => '60px',
            'height' => '30px'
        ];

        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodByMoneiCode')
            ->with('applePay')
            ->willReturn($methodDetails);

        $result = $this->paymentMethodFormatter->getPaymentMethodDimensions($paymentInfo);
        $this->assertEquals(['width' => '60px', 'height' => '30px'], $result);

        // With card brand
        $paymentInfo = ['brand' => 'visa'];
        $this
            ->paymentMethodHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodDimensions')
            ->with('visa')
            ->willReturn(['width' => '50px', 'height' => '25px']);

        $result = $this->paymentMethodFormatter->getPaymentMethodDimensions($paymentInfo);
        $this->assertEquals(['width' => '50px', 'height' => '25px'], $result);

        // Default dimensions for empty info
        $paymentInfo = [];
        $result = $this->paymentMethodFormatter->getPaymentMethodDimensions($paymentInfo);
        $this->assertEquals(['width' => '40px', 'height' => '24px'], $result);
    }
}

<?php

namespace Monei\MoneiPayment\Test\Unit\Helper;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Helper\PaymentMethod;
use Monei\MoneiPayment\Helper\PaymentMethodFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for PaymentMethodFormatter helper
 */
class PaymentMethodFormatterTest extends TestCase
{
    /**
     * @var PaymentMethodFormatter
     */
    private $paymentMethodFormatter;

    /**
     * @var PaymentMethod|MockObject
     */
    private $paymentMethodHelperMock;

    protected function setUp(): void
    {
        $this->paymentMethodHelperMock = $this->createMock(PaymentMethod::class);
        $this->paymentMethodFormatter = new PaymentMethodFormatter($this->paymentMethodHelperMock);
    }

    /**
     * Test formatting card payment method display
     */
    public function testFormatPaymentMethodDisplayForCard(): void
    {
        // Setup
        $paymentInfo = [
            'method' => 'card',
            'brand' => 'visa',
            'type' => 'credit',
            'last4' => '4242'
        ];

        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodName')
            ->with('visa')
            ->willReturn('Visa');

        // Execute
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify
        $this->assertEquals('Visa Credit •••• 4242', $result);
    }

    /**
     * Test formatting Bizum payment method display
     */
    public function testFormatPaymentMethodDisplayForBizum(): void
    {
        // Setup
        $paymentInfo = [
            'method' => PaymentMethods::PAYMENT_METHODS_BIZUM,
            'phoneNumber' => '123456789'
        ];

        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodByMoneiCode')
            ->with(PaymentMethods::PAYMENT_METHODS_BIZUM)
            ->willReturn(['name' => 'Bizum']);

        // Execute
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify
        $this->assertEquals('Bizum •••••789', $result);
    }

    /**
     * Test formatting PayPal payment method display
     */
    public function testFormatPaymentMethodDisplayForPayPal(): void
    {
        // Setup
        $paymentInfo = [
            'method' => PaymentMethods::PAYMENT_METHODS_PAYPAL,
            'orderId' => 'PP123456',
            'payerId' => 'CUST123',
            'email' => 'customer@example.com',
            'name' => 'John Doe'
        ];

        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodByMoneiCode')
            ->with(PaymentMethods::PAYMENT_METHODS_PAYPAL)
            ->willReturn(null);

        // Execute
        $result = $this->paymentMethodFormatter->formatPaymentMethodDisplay($paymentInfo);

        // Verify
        $this->assertStringContainsString('PayPal', $result);
        $this->assertStringContainsString('PP123456', $result);
        $this->assertStringContainsString('CUST123', $result);
        $this->assertStringContainsString('customer@example.com', $result);
        $this->assertStringContainsString('John Doe', $result);
    }

    /**
     * Test formatting wallet display
     */
    public function testFormatWalletDisplay(): void
    {
        // Setup
        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodByMoneiCode')
            ->with(PaymentMethods::PAYMENT_METHODS_APPLE_PAY)
            ->willReturn(['name' => 'Apple Pay']);

        // Execute
        $result = $this->paymentMethodFormatter->formatWalletDisplay(PaymentMethods::PAYMENT_METHODS_APPLE_PAY);

        // Verify
        $this->assertEquals('Apple Pay', $result);
    }

    /**
     * Test formatting phone number
     */
    public function testFormatPhoneNumber(): void
    {
        // Test international format
        $this->assertEquals('+34 123 456 789', $this->paymentMethodFormatter->formatPhoneNumber('+34123456789'));

        // Test national format
        $this->assertEquals('123 456 789', $this->paymentMethodFormatter->formatPhoneNumber('123456789'));

        // Test with non-digit characters
        $this->assertEquals('123 456 789', $this->paymentMethodFormatter->formatPhoneNumber('123-456-789'));
    }

    /**
     * Test getting payment method icon
     */
    public function testGetPaymentMethodIcon(): void
    {
        // Setup for card payment
        $paymentInfo = [
            'method' => 'card',
            'brand' => 'visa'
        ];

        $this
            ->paymentMethodHelperMock
            ->method('getIconFromPaymentType')
            ->with('card', 'visa')
            ->willReturn('https://example.com/icons/visa.png');

        // Execute
        $result = $this->paymentMethodFormatter->getPaymentMethodIcon($paymentInfo);

        // Verify
        $this->assertEquals('https://example.com/icons/visa.png', $result);
    }

    /**
     * Test getting payment method dimensions
     */
    public function testGetPaymentMethodDimensions(): void
    {
        // Setup
        $paymentInfo = [
            'method' => PaymentMethods::PAYMENT_METHODS_BIZUM
        ];

        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodByMoneiCode')
            ->with(PaymentMethods::PAYMENT_METHODS_BIZUM)
            ->willReturn([
                'name' => 'Bizum',
                'width' => '50px',
                'height' => '30px'
            ]);

        // Execute
        $result = $this->paymentMethodFormatter->getPaymentMethodDimensions($paymentInfo);

        // Verify
        $this->assertEquals([
            'width' => '50px',
            'height' => '30px'
        ], $result);
    }

    /**
     * Test generating payment method icon HTML
     */
    public function testGetPaymentMethodIconHtml(): void
    {
        // Setup
        $paymentInfo = [
            'method' => 'card',
            'brand' => 'visa'
        ];

        $this
            ->paymentMethodHelperMock
            ->method('getIconFromPaymentType')
            ->with('card', 'visa')
            ->willReturn('https://example.com/icons/visa.png');

        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodName')
            ->with('visa')
            ->willReturn('Visa');

        $this
            ->paymentMethodHelperMock
            ->method('getPaymentMethodDimensions')
            ->with('visa')
            ->willReturn([
                'width' => '40px',
                'height' => '24px'
            ]);

        // We're not testing the actual HTML output here, just that the method returns a non-empty string
        // that contains the expected attributes
        $result = $this->paymentMethodFormatter->getPaymentMethodIconHtml($paymentInfo);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('https://example.com/icons/visa.png', $result);
        $this->assertStringContainsString('Visa', $result);
        $this->assertStringContainsString('40px', $result);
        $this->assertStringContainsString('24px', $result);
    }
}

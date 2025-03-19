<?php declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Payment;

use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Model\Payment\Monei;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Monei\MoneiPayment\Model\Payment\Monei
 */
class MoneiTest extends TestCase
{
    /**
     * Test status constants are defined correctly
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('monei_pending', Monei::STATUS_MONEI_PENDING);
        $this->assertEquals('monei_authorized', Monei::STATUS_MONEI_AUTHORIZED);
        $this->assertEquals('monei_expired', Monei::STATUS_MONEI_EXPIRED);
        $this->assertEquals('monei_failed', Monei::STATUS_MONEI_FAILED);
        $this->assertEquals('monei_succeeded', Monei::STATUS_MONEI_SUCCEEDED);
        $this->assertEquals('monei_succeeded', Monei::STATUS_MONEI_SUCCEDED);  // Deprecated but kept for BC
        $this->assertEquals('monei_partially_refunded', Monei::STATUS_MONEI_PARTIALLY_REFUNDED);
        $this->assertEquals('monei_refunded', Monei::STATUS_MONEI_REFUNDED);
    }

    /**
     * Test payment method code constants
     */
    public function testPaymentMethodCodeConstants(): void
    {
        $this->assertEquals('monei', Monei::REDIRECT_CODE);
        $this->assertEquals('monei_card', Monei::CARD_CODE);
        $this->assertEquals('monei_cc_vault', Monei::CC_VAULT_CODE);
        $this->assertEquals('card', Monei::VAULT_TYPE);
        $this->assertEquals('monei_bizum', Monei::BIZUM_CODE);
        $this->assertEquals('monei_google_apple', Monei::GOOGLE_APPLE_CODE);
        $this->assertEquals('monei_multibanco_redirect', Monei::MULTIBANCO_REDIRECT_CODE);
        $this->assertEquals('monei_mbway_redirect', Monei::MBWAY_REDIRECT_CODE);
        $this->assertEquals('monei_paypal', Monei::PAYPAL_CODE);
    }

    /**
     * Test the MONEI payment methods array
     */
    public function testPaymentMethodsArray(): void
    {
        $expectedMethods = [
            Monei::REDIRECT_CODE,
            Monei::CARD_CODE,
            Monei::CC_VAULT_CODE,
            Monei::BIZUM_CODE,
            Monei::GOOGLE_APPLE_CODE,
            Monei::MULTIBANCO_REDIRECT_CODE,
            Monei::MBWAY_REDIRECT_CODE,
            Monei::PAYPAL_CODE,
        ];

        $this->assertEquals($expectedMethods, Monei::PAYMENT_METHODS_MONEI);
    }

    /**
     * Test MONEI Google and Apple Pay constants
     */
    public function testGoogleAppleConstants(): void
    {
        $this->assertEquals(PaymentMethods::PAYMENT_METHODS_GOOGLE_PAY, Monei::MONEI_GOOGLE_CODE);
        $this->assertEquals(PaymentMethods::PAYMENT_METHODS_APPLE_PAY, Monei::MONEI_APPLE_CODE);
    }

    /**
     * Test payment method map
     */
    public function testPaymentMethodMap(): void
    {
        $expectedMap = [
            Monei::BIZUM_CODE => [PaymentMethods::PAYMENT_METHODS_BIZUM],
            Monei::GOOGLE_APPLE_CODE => [Monei::MONEI_GOOGLE_CODE, Monei::MONEI_APPLE_CODE],
            Monei::CARD_CODE => [PaymentMethods::PAYMENT_METHODS_CARD],
            Monei::MULTIBANCO_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MULTIBANCO],
            Monei::MBWAY_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MBWAY],
            Monei::PAYPAL_CODE => [PaymentMethods::PAYMENT_METHODS_PAYPAL],
        ];

        $this->assertEquals($expectedMap, Monei::PAYMENT_METHOD_MAP);
    }

    /**
     * Test redirect payment map
     */
    public function testRedirectPaymentMap(): void
    {
        $expectedMap = [
            Monei::MULTIBANCO_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MULTIBANCO],
            Monei::MBWAY_REDIRECT_CODE => [PaymentMethods::PAYMENT_METHODS_MBWAY],
        ];

        $this->assertEquals($expectedMap, Monei::REDIRECT_PAYMENT_MAP);
    }
}

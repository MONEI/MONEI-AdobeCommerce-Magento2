<?php

/**
 * Test for CheckoutConfigProvider model
 *
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Api\Config\AllMoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiBizumPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaypalPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;
use Monei\MoneiPayment\Helper\PaymentMethod;
use Monei\MoneiPayment\Model\CheckoutConfigProvider;
use Monei\MoneiPayment\Service\Shared\ApplePayAvailability;
use Monei\MoneiPayment\Service\Shared\GooglePayAvailability;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for CheckoutConfigProvider model
 */
class CheckoutConfigProviderTest extends TestCase
{
    /**
     * CheckoutConfigProvider instance
     *
     * @var CheckoutConfigProvider
     */
    private $_checkoutConfigProvider;

    /**
     * URL builder mock
     *
     * @var MockObject|UrlInterface
     */
    private $_urlBuilderMock;

    /**
     * All Monei payment config mock
     *
     * @var MockObject|AllMoneiPaymentModuleConfigInterface
     */
    private $_allMoneiPaymentModuleConfigMock;

    /**
     * Monei payment config mock
     *
     * @var MockObject|MoneiPaymentModuleConfigInterface
     */
    private $_moneiPaymentConfigMock;

    /**
     * Monei card payment config mock
     *
     * @var MockObject|MoneiCardPaymentModuleConfigInterface
     */
    private $_moneiCardPaymentConfigMock;

    /**
     * Monei Google/Apple payment config mock
     *
     * @var MockObject|MoneiGoogleApplePaymentModuleConfigInterface
     */
    private $_moneiGoogleApplePaymentConfigMock;

    /**
     * Monei Bizum payment config mock
     *
     * @var MockObject|MoneiBizumPaymentModuleConfigInterface
     */
    private $_moneiBizumPaymentModuleConfigMock;

    /**
     * Monei PayPal payment config mock
     *
     * @var MockObject|MoneiPaypalPaymentModuleConfigInterface
     */
    private $_moneiPaypalPaymentModuleConfigMock;

    /**
     * Google Pay availability mock
     *
     * @var MockObject|GooglePayAvailability
     */
    private $_googlePayAvailabilityMock;

    /**
     * Apple Pay availability mock
     *
     * @var MockObject|ApplePayAvailability
     */
    private $_applePayAvailabilityMock;

    /**
     * Store manager mock
     *
     * @var MockObject|StoreManagerInterface
     */
    private $_storeManagerMock;

    /**
     * Payment method helper mock
     *
     * @var MockObject|PaymentMethod
     */
    private $_paymentMethodHelperMock;

    /**
     * Get payment methods service mock
     *
     * @var MockObject|GetPaymentMethodsInterface
     */
    private $_getPaymentMethodsMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->_allMoneiPaymentModuleConfigMock = $this->createMock(AllMoneiPaymentModuleConfigInterface::class);
        $this->_moneiPaymentConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->_moneiCardPaymentConfigMock = $this->createMock(MoneiCardPaymentModuleConfigInterface::class);
        $this->_moneiGoogleApplePaymentConfigMock = $this->createMock(MoneiGoogleApplePaymentModuleConfigInterface::class);
        $this->_moneiBizumPaymentModuleConfigMock = $this->createMock(MoneiBizumPaymentModuleConfigInterface::class);
        $this->_moneiPaypalPaymentModuleConfigMock = $this->createMock(MoneiPaypalPaymentModuleConfigInterface::class);
        $this->_googlePayAvailabilityMock = $this->createMock(GooglePayAvailability::class);
        $this->_applePayAvailabilityMock = $this->createMock(ApplePayAvailability::class);
        $this->_storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->_paymentMethodHelperMock = $this->createMock(PaymentMethod::class);
        $this->_getPaymentMethodsMock = $this->createMock(GetPaymentMethodsInterface::class);

        $this->_checkoutConfigProvider = new CheckoutConfigProvider(
            $this->_urlBuilderMock,
            $this->_allMoneiPaymentModuleConfigMock,
            $this->_moneiPaymentConfigMock,
            $this->_moneiCardPaymentConfigMock,
            $this->_moneiGoogleApplePaymentConfigMock,
            $this->_moneiBizumPaymentModuleConfigMock,
            $this->_moneiPaypalPaymentModuleConfigMock,
            $this->_googlePayAvailabilityMock,
            $this->_applePayAvailabilityMock,
            $this->_storeManagerMock,
            $this->_paymentMethodHelperMock,
            $this->_getPaymentMethodsMock
        );
    }

    /**
     * Test getConfig method
     *
     * @return void
     */
    public function testGetConfig(): void
    {
        // Mock store
        $storeMock = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $storeMock
            ->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        $this
            ->_storeManagerMock
            ->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        // Mock isEnabled method
        $this
            ->_moneiPaymentConfigMock
            ->expects($this->any())
            ->method('isEnabled')
            ->willReturn(true);

        // Mock getTitle method
        $this
            ->_moneiPaymentConfigMock
            ->expects($this->any())
            ->method('getTitle')
            ->willReturn('MONEI Payment');

        // Mock getDescription method
        $this
            ->_moneiPaymentConfigMock
            ->expects($this->any())
            ->method('getDescription')
            ->willReturn('Pay with MONEI');

        // Mock getApiKey method
        $this
            ->_moneiPaymentConfigMock
            ->expects($this->any())
            ->method('getApiKey')
            ->willReturn('api_key_test_123');

        // Mock getAccountId method
        $this
            ->_moneiPaymentConfigMock
            ->expects($this->atLeastOnce())
            ->method('getAccountId')
            ->willReturn('account_123');

        // Mock URL builder
        $this
            ->_urlBuilderMock
            ->expects($this->atLeastOnce())
            ->method('getUrl')
            ->willReturn('https://example.com/monei/payment/action');

        // Mock payment methods response
        $paymentMethodsResponse = $this->createMock(PaymentMethods::class);
        $paymentMethodsResponse
            ->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn(['card', 'paypal', 'bizum']);

        $this
            ->_getPaymentMethodsMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn($paymentMethodsResponse);

        // Mock payment method helper
        $paymentMethodDetails = [
            'default' => [
                'name' => 'Card',
                'icon' => 'http://example.com/img/cards/default.svg',
                'width' => '40px',
                'height' => '22px'
            ],
            'visa' => [
                'name' => 'Visa',
                'icon' => 'http://example.com/img/cards/visa.svg',
                'width' => '40px',
                'height' => '22px'
            ],
            'mastercard' => [
                'name' => 'MasterCard',
                'icon' => 'http://example.com/img/cards/mastercard.svg',
                'width' => '40px',
                'height' => '22px'
            ],
            'paypal' => [
                'name' => 'PayPal',
                'icon' => 'http://example.com/img/paypal.svg',
                'width' => '83px',
                'height' => '22px'
            ],
            'bizum' => [
                'name' => 'Bizum',
                'icon' => 'http://example.com/img/bizum.svg',
                'width' => '70px',
                'height' => '22px'
            ]
        ];

        $this
            ->_paymentMethodHelperMock
            ->expects($this->any())
            ->method('getPaymentMethodDetails')
            ->willReturn($paymentMethodDetails);

        $this
            ->_paymentMethodHelperMock
            ->expects($this->any())
            ->method('getIconFromPaymentType')
            ->willReturn('http://example.com/img/cards/default.svg');

        $this
            ->_paymentMethodHelperMock
            ->expects($this->any())
            ->method('getPaymentMethodDimensions')
            ->willReturn(['width' => '40px', 'height' => '22px']);

        $config = $this->_checkoutConfigProvider->getConfig();

        $this->assertArrayHasKey('payment', $config);
        $this->assertArrayHasKey('monei', $config['payment']);
        $this->assertArrayHasKey('monei_card', $config['payment']);
        $this->assertArrayHasKey('monei_bizum', $config['payment']);
        $this->assertArrayHasKey('monei_google_apple', $config['payment']);
        $this->assertArrayHasKey('monei_paypal', $config['payment']);

        // Check account ID
        $this->assertEquals('account_123', $config['payment']['monei_card']['accountId']);

        // Check API key
        $this->assertEquals('api_key_test_123', $config['moneiApiKey']);

        // Check redirect URLs
        $this->assertEquals('https://example.com/monei/payment/action', $config['payment']['monei']['redirectUrl']);
        $this->assertEquals('https://example.com/monei/payment/action', $config['payment']['monei']['cancelOrderUrl']);
        $this->assertEquals('https://example.com/monei/payment/action', $config['payment']['monei']['completeUrl']);
    }
}

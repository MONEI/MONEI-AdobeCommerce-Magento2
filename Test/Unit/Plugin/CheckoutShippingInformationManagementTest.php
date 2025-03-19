<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\PaymentMethodInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\Data\AddressInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\CheckoutShippingInformationManagement as CheckoutShippingInformationManagementPlugin;
use Monei\MoneiPayment\Service\Shared\CountryPaymentMethods;
use Monei\MoneiPayment\Service\Shared\PaymentMethodMap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutShippingInformationManagementTest extends TestCase
{
    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $moneiPaymentModuleConfigMock;

    /**
     * @var CountryPaymentMethods|MockObject
     */
    private $countryPaymentMethodsMock;

    /**
     * @var PaymentMethodMap|MockObject
     */
    private $paymentMethodMapMock;

    /**
     * @var CheckoutShippingInformationManagementPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->moneiPaymentModuleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->countryPaymentMethodsMock = $this->createMock(CountryPaymentMethods::class);
        $this->paymentMethodMapMock = $this->createMock(PaymentMethodMap::class);

        $this->plugin = new CheckoutShippingInformationManagementPlugin(
            $this->moneiPaymentModuleConfigMock,
            $this->countryPaymentMethodsMock,
            $this->paymentMethodMapMock
        );
    }

    /**
     * Test when API keys are not configured
     */
    public function testAroundSaveAddressInformationWhenApiKeysNotConfigured()
    {
        $cartId = 1;
        $countryId = 'ES';

        // Mock ShippingInformationManagement
        $shippingInformationManagementMock = $this->createMock(ShippingInformationManagement::class);

        // Mock ShippingInformationInterface
        $addressInformationMock = $this->createMock(ShippingInformationInterface::class);

        // Mock ShippingAddress
        $shippingAddressMock = $this->createMock(AddressInterface::class);
        $shippingAddressMock
            ->expects($this->any())
            ->method('getCountryId')
            ->willReturn($countryId);

        $addressInformationMock
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddressMock);

        // Mock PaymentDetailsInterface
        $paymentDetailsMock = $this->createMock(PaymentDetailsInterface::class);

        // Mock proceed callable
        $proceed = function () use ($paymentDetailsMock) {
            return $paymentDetailsMock;
        };

        // Set up payment methods
        $nonMoneiPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $nonMoneiPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn('checkmo');

        $moneiPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $moneiPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn(Monei::CARD_CODE);

        $moneiBizumPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $moneiBizumPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn(Monei::BIZUM_CODE);

        $paymentMethods = [$nonMoneiPaymentMethod, $moneiPaymentMethod, $moneiBizumPaymentMethod];

        $paymentDetailsMock
            ->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn($paymentMethods);

        $paymentDetailsMock
            ->expects($this->once())
            ->method('setPaymentMethods')
            ->with([$nonMoneiPaymentMethod])
            ->willReturnSelf();

        // Configure module config mock to return empty API credentials
        $this
            ->moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('getAccountId')
            ->willReturn('');

        $this
            ->moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('getApiKey')
            ->willReturn('');

        // Execute the plugin method
        $result = $this->plugin->aroundSaveAddressInformation(
            $shippingInformationManagementMock,
            $proceed,
            $cartId,
            $addressInformationMock
        );

        $this->assertSame($paymentDetailsMock, $result);
    }

    /**
     * Test when API keys are configured and some payment methods are available for the country
     */
    public function testAroundSaveAddressInformationWithFilteredPaymentMethods()
    {
        $cartId = 1;
        $countryId = 'ES';
        $accountId = 'test_account_id';
        $apiKey = 'test_api_key';

        // Mock ShippingInformationManagement
        $shippingInformationManagementMock = $this->createMock(ShippingInformationManagement::class);

        // Mock ShippingInformationInterface
        $addressInformationMock = $this->createMock(ShippingInformationInterface::class);

        // Mock ShippingAddress
        $shippingAddressMock = $this->createMock(AddressInterface::class);
        $shippingAddressMock
            ->expects($this->any())
            ->method('getCountryId')
            ->willReturn($countryId);

        $addressInformationMock
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddressMock);

        // Mock PaymentDetailsInterface
        $paymentDetailsMock = $this->createMock(PaymentDetailsInterface::class);

        // Mock proceed callable
        $proceed = function () use ($paymentDetailsMock) {
            return $paymentDetailsMock;
        };

        // Set up payment methods
        $nonMoneiPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $nonMoneiPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn('checkmo');

        $moneiCardPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $moneiCardPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn(Monei::CARD_CODE);

        $moneiBizumPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $moneiBizumPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn(Monei::BIZUM_CODE);

        $paymentMethods = [$nonMoneiPaymentMethod, $moneiCardPaymentMethod, $moneiBizumPaymentMethod];

        $paymentDetailsMock
            ->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn($paymentMethods);

        // Configure module config mock to return API credentials
        $this
            ->moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('getAccountId')
            ->willReturn($accountId);

        $this
            ->moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('getApiKey')
            ->willReturn($apiKey);

        // Configure country payment methods mock
        $availablePaymentMethods = ['CARD', 'BIZUM'];  // Card and Bizum available in Spain
        $this
            ->countryPaymentMethodsMock
            ->expects($this->once())
            ->method('execute')
            ->with($countryId)
            ->willReturn($availablePaymentMethods);

        // Configure payment method map mock
        $this
            ->paymentMethodMapMock
            ->expects($this->exactly(3))
            ->method('execute')
            ->willReturnMap([
                ['checkmo', []],  // Non-Monei payment method
                [Monei::CARD_CODE, ['CARD']],  // Card payment method
                [Monei::BIZUM_CODE, ['BIZUM']]  // Bizum payment method
            ]);

        // Only set filtered payment methods
        $paymentDetailsMock
            ->expects($this->once())
            ->method('setPaymentMethods')
            ->with([$nonMoneiPaymentMethod, $moneiCardPaymentMethod, $moneiBizumPaymentMethod])
            ->willReturnSelf();

        // Execute the plugin method
        $result = $this->plugin->aroundSaveAddressInformation(
            $shippingInformationManagementMock,
            $proceed,
            $cartId,
            $addressInformationMock
        );

        $this->assertSame($paymentDetailsMock, $result);
    }

    /**
     * Test when API keys are configured but payment method is not available for the country
     */
    public function testAroundSaveAddressInformationWithUnavailablePaymentMethod()
    {
        $cartId = 1;
        $countryId = 'US';  // United States
        $accountId = 'test_account_id';
        $apiKey = 'test_api_key';

        // Mock ShippingInformationManagement
        $shippingInformationManagementMock = $this->createMock(ShippingInformationManagement::class);

        // Mock ShippingInformationInterface
        $addressInformationMock = $this->createMock(ShippingInformationInterface::class);

        // Mock ShippingAddress
        $shippingAddressMock = $this->createMock(AddressInterface::class);
        $shippingAddressMock
            ->expects($this->any())
            ->method('getCountryId')
            ->willReturn($countryId);

        $addressInformationMock
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shippingAddressMock);

        // Mock PaymentDetailsInterface
        $paymentDetailsMock = $this->createMock(PaymentDetailsInterface::class);

        // Mock proceed callable
        $proceed = function () use ($paymentDetailsMock) {
            return $paymentDetailsMock;
        };

        // Set up payment methods
        $nonMoneiPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $nonMoneiPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn('checkmo');

        $moneiBizumPaymentMethod = $this
            ->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $moneiBizumPaymentMethod
            ->expects($this->any())
            ->method('getCode')
            ->willReturn(Monei::BIZUM_CODE);

        $paymentMethods = [$nonMoneiPaymentMethod, $moneiBizumPaymentMethod];

        $paymentDetailsMock
            ->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn($paymentMethods);

        // Configure module config mock to return API credentials
        $this
            ->moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('getAccountId')
            ->willReturn($accountId);

        $this
            ->moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('getApiKey')
            ->willReturn($apiKey);

        // Configure country payment methods mock - Bizum not available in US
        $availablePaymentMethods = ['CARD'];  // Only Card available in US
        $this
            ->countryPaymentMethodsMock
            ->expects($this->once())
            ->method('execute')
            ->with($countryId)
            ->willReturn($availablePaymentMethods);

        // Configure payment method map mock
        $this
            ->paymentMethodMapMock
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnMap([
                ['checkmo', []],  // Non-Monei payment method
                [Monei::BIZUM_CODE, ['BIZUM']]  // Bizum payment method (not available in US)
            ]);

        // Only set filtered payment methods (only non-Monei payment method)
        $paymentDetailsMock
            ->expects($this->once())
            ->method('setPaymentMethods')
            ->with([$nonMoneiPaymentMethod])
            ->willReturnSelf();

        // Execute the plugin method
        $result = $this->plugin->aroundSaveAddressInformation(
            $shippingInformationManagementMock,
            $proceed,
            $cartId,
            $addressInformationMock
        );

        $this->assertSame($paymentDetailsMock, $result);
    }
}

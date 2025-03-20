<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Shared\ApplePayAvailability;
use Monei\MoneiPayment\Service\Shared\AvailablePaymentMethods;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ApplePayAvailability service.
 */
class ApplePayAvailabilityTest extends TestCase
{
    /**
     * @var MoneiGoogleApplePaymentModuleConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $googleAppleConfigMock;

    /**
     * @var AvailablePaymentMethods|\PHPUnit\Framework\MockObject\MockObject
     */
    private $availablePaymentMethodsMock;

    /**
     * @var ApplePayAvailability
     */
    private $applePayAvailability;

    protected function setUp(): void
    {
        $this->googleAppleConfigMock = $this->createMock(MoneiGoogleApplePaymentModuleConfigInterface::class);
        $this->availablePaymentMethodsMock = $this->createMock(AvailablePaymentMethods::class);

        $this->applePayAvailability = new ApplePayAvailability(
            $this->googleAppleConfigMock,
            $this->availablePaymentMethodsMock
        );
    }

    /**
     * Test when Apple Pay is enabled in config and available in payment methods
     */
    public function testExecuteWhenApplePayIsEnabled(): void
    {
        // Configure Apple Pay to be enabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(true);

        // Set Apple Pay to be available in payment methods
        $availablePaymentMethods = ['card', Monei::MONEI_APPLE_CODE, 'paypal'];
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($availablePaymentMethods);

        // Test the execute method
        $result = $this->applePayAvailability->execute();

        // Apple Pay should be available
        $this->assertTrue($result);
    }

    /**
     * Test when Apple Pay is disabled in config
     */
    public function testExecuteWhenApplePayIsDisabledInConfig(): void
    {
        // Configure Apple Pay to be disabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(false);

        // Set Apple Pay to be available in payment methods (but shouldn't matter)
        $availablePaymentMethods = ['card', Monei::MONEI_APPLE_CODE, 'paypal'];
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($availablePaymentMethods);

        // Test the execute method
        $result = $this->applePayAvailability->execute();

        // Apple Pay should not be available
        $this->assertFalse($result);
    }

    /**
     * Test when Apple Pay is enabled in config but not available in payment methods
     */
    public function testExecuteWhenApplePayIsEnabledButNotAvailable(): void
    {
        // Configure Apple Pay to be enabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(true);

        // Set payment methods that don't include Apple Pay
        $availablePaymentMethods = ['card', 'paypal', 'bizum'];
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($availablePaymentMethods);

        // Test the execute method
        $result = $this->applePayAvailability->execute();

        // Apple Pay should not be available
        $this->assertFalse($result);
    }

    /**
     * Test when the payment methods service returns null or empty array
     */
    public function testExecuteWhenPaymentMethodsAreEmpty(): void
    {
        // Configure Apple Pay to be enabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(true);

        // Set empty payment methods
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn([]);

        // Test the execute method
        $result = $this->applePayAvailability->execute();

        // Apple Pay should not be available
        $this->assertFalse($result);
    }
}

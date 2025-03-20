<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Shared;

use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Shared\AvailablePaymentMethods;
use Monei\MoneiPayment\Service\Shared\GooglePayAvailability;
use PHPUnit\Framework\TestCase;

/**
 * Test class for GooglePayAvailability service.
 */
class GooglePayAvailabilityTest extends TestCase
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
     * @var GooglePayAvailability
     */
    private $googlePayAvailability;

    protected function setUp(): void
    {
        $this->googleAppleConfigMock = $this->createMock(MoneiGoogleApplePaymentModuleConfigInterface::class);
        $this->availablePaymentMethodsMock = $this->createMock(AvailablePaymentMethods::class);

        $this->googlePayAvailability = new GooglePayAvailability(
            $this->googleAppleConfigMock,
            $this->availablePaymentMethodsMock
        );
    }

    /**
     * Test when Google Pay is enabled in config and available in payment methods
     */
    public function testExecuteWhenGooglePayIsEnabled(): void
    {
        // Configure Google Pay to be enabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(true);

        // Set Google Pay to be available in payment methods
        $availablePaymentMethods = ['card', Monei::MONEI_GOOGLE_CODE, 'paypal'];
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($availablePaymentMethods);

        // Test the execute method
        $result = $this->googlePayAvailability->execute();

        // Google Pay should be available
        $this->assertTrue($result);
    }

    /**
     * Test when Google Pay is disabled in config
     */
    public function testExecuteWhenGooglePayIsDisabledInConfig(): void
    {
        // Configure Google Pay to be disabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(false);

        // Set Google Pay to be available in payment methods (but shouldn't matter)
        $availablePaymentMethods = ['card', Monei::MONEI_GOOGLE_CODE, 'paypal'];
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($availablePaymentMethods);

        // Test the execute method
        $result = $this->googlePayAvailability->execute();

        // Google Pay should not be available
        $this->assertFalse($result);
    }

    /**
     * Test when Google Pay is enabled in config but not available in payment methods
     */
    public function testExecuteWhenGooglePayIsEnabledButNotAvailable(): void
    {
        // Configure Google Pay to be enabled
        $this
            ->googleAppleConfigMock
            ->method('isEnabled')
            ->willReturn(true);

        // Set payment methods that don't include Google Pay
        $availablePaymentMethods = ['card', 'paypal', 'bizum'];
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($availablePaymentMethods);

        // Test the execute method
        $result = $this->googlePayAvailability->execute();

        // Google Pay should not be available
        $this->assertFalse($result);
    }

    /**
     * Test when the payment methods service returns null or empty array
     */
    public function testExecuteWhenPaymentMethodsAreEmpty(): void
    {
        // Configure Google Pay to be enabled
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
        $result = $this->googlePayAvailability->execute();

        // Google Pay should not be available
        $this->assertFalse($result);
    }
}

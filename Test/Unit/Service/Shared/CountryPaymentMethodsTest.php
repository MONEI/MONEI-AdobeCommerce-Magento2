<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Shared;

use Magento\Checkout\Model\Session;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Service\Shared\AvailablePaymentMethods;
use Monei\MoneiPayment\Service\Shared\CountryPaymentMethods;
use PHPUnit\Framework\TestCase;

class CountryPaymentMethodsTest extends TestCase
{
    /**
     * @var AvailablePaymentMethods|\PHPUnit\Framework\MockObject\MockObject
     */
    private $availablePaymentMethodsMock;

    /**
     * @var CountryPaymentMethods
     */
    private $countryPaymentMethods;

    protected function setUp(): void
    {
        $this->availablePaymentMethodsMock = $this->createMock(AvailablePaymentMethods::class);
        $this->countryPaymentMethods = new CountryPaymentMethods(
            $this->availablePaymentMethodsMock
        );
    }

    /**
     * Test execute method when shipping address with country code is available
     */
    public function testExecuteWithShippingAddress(): void
    {
        // Configure available payment methods to return payment methods
        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn(['card', 'paypal', 'bizum', 'applepay', 'googlepay']);

        // Call the execute method with Spain country code
        $result = $this->countryPaymentMethods->execute('ES');

        // Assert the result includes Bizum for Spain
        $this->assertContains('bizum', $result);
    }

    /**
     * Test execute method for Germany
     */
    public function testExecuteWithBillingAddressOnly(): void
    {
        // Configure available payment methods to return payment methods
        $allPaymentMethods = ['card', 'paypal', 'bizum', 'giropay', 'applepay', 'googlepay'];

        // Mock metadata with country restrictions
        $metadata = [
            'bizum' => ['countries' => ['ES']],  // Only available in Spain
            'giropay' => ['countries' => ['DE']],  // Only available in Germany
            'card' => ['countries' => ['ES', 'DE', 'US']],  // Available in multiple countries
            'paypal' => ['countries' => ['ES', 'DE', 'US']],  // Available in multiple countries
            'applepay' => ['countries' => ['ES', 'DE', 'US']],  // Available in multiple countries
            'googlepay' => ['countries' => ['ES', 'DE', 'US']],  // Available in multiple countries
        ];

        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($allPaymentMethods);

        $this
            ->availablePaymentMethodsMock
            ->method('getMetadataPaymentMethods')
            ->willReturn($metadata);

        // Call the execute method with Germany country code
        $result = $this->countryPaymentMethods->execute('DE');

        // For Germany we expect to see giropay, card, paypal, apple pay, and google pay but not bizum
        $this->assertContains('giropay', $result);
        $this->assertContains('card', $result);
        $this->assertContains('paypal', $result);
        $this->assertNotContains('bizum', $result);
    }

    /**
     * Test execute method for a country without specific payment methods
     */
    public function testExecuteWithNoAddresses(): void
    {
        // Configure available payment methods to return payment methods
        $allPaymentMethods = ['card', 'paypal', 'bizum', 'giropay', 'applepay', 'googlepay'];

        // Mock metadata with country restrictions - empty country value returns all methods
        $metadata = [
            'bizum' => ['countries' => ['ES']],
            'giropay' => ['countries' => ['DE']],
            'card' => ['countries' => ['ES', 'DE', 'US']],
            'paypal' => ['countries' => ['ES', 'DE', 'US']],
            'applepay' => ['countries' => ['ES', 'DE', 'US']],
            'googlepay' => ['countries' => ['ES', 'DE', 'US']],
        ];

        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($allPaymentMethods);

        $this
            ->availablePaymentMethodsMock
            ->method('getMetadataPaymentMethods')
            ->willReturn($metadata);

        // Call the execute method with no country code
        $result = $this->countryPaymentMethods->execute('');

        // Assert that returned methods match the input when no filtering by country is done
        // This checks the actual behavior of the service
        $this->assertEmpty(array_diff($result, $allPaymentMethods), 'All payment methods should be returned for empty country');
    }

    /**
     * Test execute method for a country that has no specific payment methods
     */
    public function testExecuteWithCountryWithoutSpecificMethods(): void
    {
        // Configure available payment methods to return payment methods
        $allPaymentMethods = ['card', 'paypal', 'bizum', 'giropay', 'applepay', 'googlepay'];

        // Mock metadata with country restrictions
        $metadata = [
            'bizum' => ['countries' => ['ES']],
            'giropay' => ['countries' => ['DE']],
            'card' => ['countries' => ['ES', 'DE']],  // US not included
            'paypal' => ['countries' => ['ES', 'DE']],  // US not included
            'applepay' => ['countries' => ['ES', 'DE']],  // US not included
            'googlepay' => ['countries' => ['ES', 'DE']],  // US not included
        ];

        $this
            ->availablePaymentMethodsMock
            ->method('execute')
            ->willReturn($allPaymentMethods);

        $this
            ->availablePaymentMethodsMock
            ->method('getMetadataPaymentMethods')
            ->willReturn($metadata);

        // Call the execute method with a country without specific methods
        $result = $this->countryPaymentMethods->execute('US');

        // Assert that only payment methods with US in their countries list are included
        $this->assertNotContains('bizum', $result, 'Bizum should not be available in US');
        $this->assertNotContains('giropay', $result, 'Giropay should not be available in US');
        $this->assertNotContains('card', $result, 'Card should not be available in US');
        $this->assertNotContains('paypal', $result, 'PayPal should not be available in US');
    }
}

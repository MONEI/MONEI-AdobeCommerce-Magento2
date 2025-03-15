<?php

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Model\Order\StatusLabel;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\SalesOrderStatusLabel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for SalesOrderStatusLabel plugin
 */
class SalesOrderStatusLabelTest extends TestCase
{
    /**
     * @var SalesOrderStatusLabel
     */
    private $plugin;

    /**
     * @var State|MockObject
     */
    private $appStateMock;

    /**
     * @var StatusLabel|MockObject
     */
    private $statusLabelMock;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->appStateMock = $this->createMock(State::class);
        $this->statusLabelMock = $this->createMock(StatusLabel::class);

        $this->plugin = new SalesOrderStatusLabel($this->appStateMock);
    }

    /**
     * Test that MONEI status codes are mapped to Magento status labels in frontend
     */
    public function testStatusMappingInFrontend(): void
    {
        // Data from provider
        $moneiStatus = Monei::STATUS_MONEI_PENDING;
        $expectedLabel = 'Pending';

        // Set up app state mock to return frontend area
        $this->appStateMock->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        // Call the plugin
        $result = $this->plugin->afterGetStatusLabel(
            $this->statusLabelMock,
            'Original Label',
            $moneiStatus
        );

        // The result is a string (after __() translation)
        $this->assertIsString($result);
        // Compare the expected label
        $this->assertEquals($expectedLabel, $result);
    }

    /**
     * Test that original status labels are used in admin area
     */
    public function testOriginalStatusLabelsInAdmin(): void
    {
        // Setup test data
        $moneiStatus = Monei::STATUS_MONEI_PENDING;

        // Set up app state mock to return admin area
        $this->appStateMock->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);

        // Define an original label that would be returned by Magento
        $originalLabel = 'Original MONEI Label';

        // Call the plugin
        $result = $this->plugin->afterGetStatusLabel(
            $this->statusLabelMock,
            $originalLabel,
            $moneiStatus
        );

        // The plugin should return the original label unchanged in admin area
        $this->assertEquals($originalLabel, $result);
    }

    /**
     * Test that exception handling works correctly
     */
    public function testExceptionHandling(): void
    {
        // Set up app state mock to throw an exception
        $this->appStateMock->method('getAreaCode')->willThrowException(new \Exception('Area code not available'));

        // Define an original label and status
        $originalLabel = 'Original Label';
        $moneiStatus = Monei::STATUS_MONEI_PENDING;

        // Call the plugin
        $result = $this->plugin->afterGetStatusLabel(
            $this->statusLabelMock,
            $originalLabel,
            $moneiStatus
        );

        // The plugin should return the original label when an exception occurs
        $this->assertEquals($originalLabel, $result);
    }

    /**
     * Test that unmapped statuses are left unchanged
     */
    public function testUnmappedStatusUnchanged(): void
    {
        // Set up app state mock to return frontend area
        $this->appStateMock->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        // Define an original label and a non-MONEI status
        $originalLabel = 'Original Label';
        $unmappedStatus = 'custom_status_not_in_mapping';

        // Call the plugin
        $result = $this->plugin->afterGetStatusLabel(
            $this->statusLabelMock,
            $originalLabel,
            $unmappedStatus
        );

        // The plugin should return the original label for unmapped statuses
        $this->assertEquals($originalLabel, $result);
    }

    /**
     * Data provider for MONEI status mapping tests
     *
     * @return array
     */
    public function moneiStatusDataProvider(): array
    {
        return [
            [Monei::STATUS_MONEI_PENDING, 'Pending'],
            [Monei::STATUS_MONEI_AUTHORIZED, 'Pending Payment'],
            [Monei::STATUS_MONEI_EXPIRED, 'Canceled'],
            [Monei::STATUS_MONEI_FAILED, 'Canceled'],
            [Monei::STATUS_MONEI_SUCCEEDED, 'Processing'],
            [Monei::STATUS_MONEI_PARTIALLY_REFUNDED, 'Processing'],
            [Monei::STATUS_MONEI_REFUNDED, 'Closed'],
        ];
    }
}

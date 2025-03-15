<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Sales\Model\Order\Config;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Plugin\SalesOrderConfig;
use PHPUnit\Framework\TestCase;

class SalesOrderConfigTest extends TestCase
{
    /**
     * @var SalesOrderConfig
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->plugin = new SalesOrderConfig();
    }

    /**
     * Test that MONEI statuses are added to visible on front statuses
     */
    public function testAfterGetVisibleOnFrontStatuses()
    {
        // Default visible statuses
        $defaultStatuses = [
            'processing',
            'complete',
            'closed'
        ];
        
        // Create subject mock
        $configMock = $this->createMock(Config::class);
        
        // Execute the plugin method
        $result = $this->plugin->afterGetVisibleOnFrontStatuses($configMock, $defaultStatuses);
        
        // Expected MONEI statuses that should be added
        $expectedMoneiStatuses = [
            Monei::STATUS_MONEI_PENDING,
            Monei::STATUS_MONEI_AUTHORIZED,
            Monei::STATUS_MONEI_EXPIRED,
            Monei::STATUS_MONEI_FAILED,
            Monei::STATUS_MONEI_SUCCEEDED,
            Monei::STATUS_MONEI_PARTIALLY_REFUNDED,
            Monei::STATUS_MONEI_REFUNDED
        ];
        
        // Assert that all default statuses are still present
        foreach ($defaultStatuses as $status) {
            $this->assertContains($status, $result, "Default status '$status' should be preserved");
        }
        
        // Assert that all MONEI statuses are added
        foreach ($expectedMoneiStatuses as $status) {
            $this->assertContains($status, $result, "MONEI status '$status' should be added");
        }
        
        // Assert that the total count is correct (default + MONEI statuses)
        $this->assertCount(
            count($defaultStatuses) + count($expectedMoneiStatuses),
            $result,
            "Result should contain all default and MONEI statuses"
        );
    }
}
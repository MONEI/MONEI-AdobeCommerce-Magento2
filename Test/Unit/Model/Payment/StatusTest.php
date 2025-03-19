<?php declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Payment;

use Monei\MoneiPayment\Model\Payment\Status;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Monei\MoneiPayment\Model\Payment\Status
 */
class StatusTest extends TestCase
{
    /**
     * Test that constants are defined correctly
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('PENDING', Status::PENDING);
        $this->assertEquals('AUTHORIZED', Status::AUTHORIZED);
        $this->assertEquals('EXPIRED', Status::EXPIRED);
        $this->assertEquals('CANCELED', Status::CANCELED);
        $this->assertEquals('FAILED', Status::FAILED);
        $this->assertEquals('SUCCEEDED', Status::SUCCEEDED);
        $this->assertEquals('PARTIALLY_REFUNDED', Status::PARTIALLY_REFUNDED);
        $this->assertEquals('REFUNDED', Status::REFUNDED);
    }

    /**
     * Test the Magento status map constants
     */
    public function testMagentoStatusMap(): void
    {
        $expectedMap = [
            Status::PENDING => 'monei_pending',
            Status::AUTHORIZED => 'monei_authorized',
            Status::EXPIRED => 'monei_expired',
            Status::CANCELED => 'monei_canceled',
            Status::FAILED => 'monei_failed',
            Status::SUCCEEDED => 'monei_succeeded',
            Status::PARTIALLY_REFUNDED => 'monei_partially_refunded',
            Status::REFUNDED => 'monei_refunded',
        ];

        $this->assertEquals($expectedMap, Status::MAGENTO_STATUS_MAP);
    }

    /**
     * Test getting Magento status for MONEI payment status
     */
    public function testGetMagentoStatus(): void
    {
        $this->assertEquals('monei_pending', Status::getMagentoStatus(Status::PENDING));
        $this->assertEquals('monei_authorized', Status::getMagentoStatus(Status::AUTHORIZED));
        $this->assertEquals('monei_expired', Status::getMagentoStatus(Status::EXPIRED));
        $this->assertEquals('monei_canceled', Status::getMagentoStatus(Status::CANCELED));
        $this->assertEquals('monei_failed', Status::getMagentoStatus(Status::FAILED));
        $this->assertEquals('monei_succeeded', Status::getMagentoStatus(Status::SUCCEEDED));
        $this->assertEquals('monei_partially_refunded', Status::getMagentoStatus(Status::PARTIALLY_REFUNDED));
        $this->assertEquals('monei_refunded', Status::getMagentoStatus(Status::REFUNDED));

        // Test with invalid status
        $this->assertNull(Status::getMagentoStatus('invalid_status'));
    }

    /**
     * Test if a status is final
     */
    public function testIsFinalStatus(): void
    {
        // Final statuses
        $this->assertTrue(Status::isFinalStatus(Status::SUCCEEDED));
        $this->assertTrue(Status::isFinalStatus(Status::FAILED));
        $this->assertTrue(Status::isFinalStatus(Status::CANCELED));
        $this->assertTrue(Status::isFinalStatus(Status::EXPIRED));
        $this->assertTrue(Status::isFinalStatus(Status::REFUNDED));

        // Non-final statuses
        $this->assertFalse(Status::isFinalStatus(Status::PENDING));
        $this->assertFalse(Status::isFinalStatus(Status::AUTHORIZED));
        $this->assertFalse(Status::isFinalStatus(Status::PARTIALLY_REFUNDED));
    }

    /**
     * Test if a status is successful
     */
    public function testIsSuccessfulStatus(): void
    {
        // Successful statuses
        $this->assertTrue(Status::isSuccessfulStatus(Status::SUCCEEDED));
        $this->assertTrue(Status::isSuccessfulStatus(Status::PARTIALLY_REFUNDED));

        // Non-successful statuses
        $this->assertFalse(Status::isSuccessfulStatus(Status::PENDING));
        $this->assertFalse(Status::isSuccessfulStatus(Status::AUTHORIZED));
        $this->assertFalse(Status::isSuccessfulStatus(Status::EXPIRED));
        $this->assertFalse(Status::isSuccessfulStatus(Status::CANCELED));
        $this->assertFalse(Status::isSuccessfulStatus(Status::FAILED));
        $this->assertFalse(Status::isSuccessfulStatus(Status::REFUNDED));
    }

    /**
     * Test getting all available statuses
     */
    public function testGetAllStatuses(): void
    {
        $statuses = Status::getAllStatuses();
        $this->assertIsArray($statuses);
        $this->assertContains(Status::PENDING, $statuses);
        $this->assertContains(Status::AUTHORIZED, $statuses);
        $this->assertContains(Status::EXPIRED, $statuses);
        $this->assertContains(Status::CANCELED, $statuses);
        $this->assertContains(Status::FAILED, $statuses);
        $this->assertContains(Status::SUCCEEDED, $statuses);
        $this->assertContains(Status::PARTIALLY_REFUNDED, $statuses);
        $this->assertContains(Status::REFUNDED, $statuses);
    }
}

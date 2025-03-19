<?php

/**
 * Unit tests for CancelReason model
 *
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Monei\MoneiPayment\Model\Config\Source\CancelReason;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for CancelReason source model
 *
 * php version 8.1
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
class CancelReasonTest extends TestCase
{
    /**
     * CancelReason instance
     *
     * @var CancelReason
     */
    private $_cancelReason;

    /**
     * Set up
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_cancelReason = new CancelReason();
    }

    /**
     * Test that CancelReason implements OptionSourceInterface
     *
     * @return void
     */
    public function testImplementsOptionSourceInterface(): void
    {
        $this->assertInstanceOf(OptionSourceInterface::class, $this->_cancelReason);
    }

    /**
     * Test toOptionArray method returns expected structure and values
     *
     * @return void
     */
    public function testToOptionArray(): void
    {
        $result = $this->_cancelReason->toOptionArray();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        foreach ($result as $option) {
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('value', $option);
        }

        $values = array_column($result, 'value');
        $this->assertContains('duplicated', $values);
        $this->assertContains('fraudulent', $values);
        $this->assertContains('requested_by_customer', $values);
    }
}

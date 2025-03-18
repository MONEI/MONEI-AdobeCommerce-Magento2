<?php

/**
 * Test case for RefundReasons ViewModel.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Monei\MoneiPayment\Model\Config\Source\CancelReason;
use Monei\MoneiPayment\ViewModel\RefundReasons;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for RefundReasons ViewModel.
 *
 * @category Monei
 * @package  Monei\MoneiPayment
 * @author   Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license  https://opensource.org/license/mit/ MIT License
 * @link     https://monei.com/
 */
class RefundReasonsTest extends TestCase
{
    /**
     * RefundReasons instance being tested
     *
     * @var RefundReasons
     */
    private $_refundReasons;

    /**
     * Mock of CancelReason
     *
     * @var CancelReason|MockObject
     */
    private $_cancelReasonMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_cancelReasonMock = $this->createMock(CancelReason::class);
        $this->_refundReasons = new RefundReasons($this->_cancelReasonMock);
    }

    /**
     * Test that RefundReasons implements ArgumentInterface
     *
     * @return void
     */
    public function testImplementsArgumentInterface(): void
    {
        $this->assertInstanceOf(
            ArgumentInterface::class,
            $this->_refundReasons
        );
    }

    /**
     * Test getData method returns expected array
     *
     * @return void
     */
    public function testGetDataReturnsReasonOptions(): void
    {
        $expectedReasons = [
            ['label' => 'Duplicated', 'value' => 'duplicated'],
            ['label' => 'Fraudulent', 'value' => 'fraudulent'],
            ['label' => 'Requested by customer', 'value' => 'requested_by_customer'],
        ];

        $this
            ->_cancelReasonMock
            ->expects($this->once())
            ->method('toOptionArray')
            ->willReturn($expectedReasons);

        $result = $this->_refundReasons->getData();

        $this->assertEquals($expectedReasons, $result);
    }
}

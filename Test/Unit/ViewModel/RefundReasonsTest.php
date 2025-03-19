<?php

/**
 * Unit tests for RefundReasons ViewModel
 *
 * @category  Monei
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\ViewModel;

use Monei\MoneiPayment\Model\Config\Source\CancelReason;
use Monei\MoneiPayment\ViewModel\RefundReasons;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for RefundReasons ViewModel
 *
 * php version 8.1
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
class RefundReasonsTest extends TestCase
{
    /**
     * Cancel reason mock
     *
     * @var CancelReason|\PHPUnit\Framework\MockObject\MockObject
     */
    private $_cancelReasonMock;

    /**
     * Refund reasons viewmodel
     *
     * @var RefundReasons
     */
    private $_refundReasons;

    /**
     * Set up
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_cancelReasonMock = $this->createMock(CancelReason::class);
        $this->_refundReasons = new RefundReasons($this->_cancelReasonMock);
    }

    /**
     * Test getData method
     *
     * @return void
     */
    public function testGetData(): void
    {
        $reasonOptions = [
            ['value' => 'duplicated', 'label' => 'Duplicated'],
            ['value' => 'fraudulent', 'label' => 'Fraudulent'],
            ['value' => 'requested_by_customer', 'label' => 'Requested by customer']
        ];

        $this
            ->_cancelReasonMock
            ->method('toOptionArray')
            ->willReturn($reasonOptions);

        $this->assertEquals($reasonOptions, $this->_refundReasons->getData());
    }
}

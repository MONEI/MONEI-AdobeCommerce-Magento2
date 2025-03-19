<?php

/**
 * Test case for PaymentInfo ViewModel.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\ViewModel\Order;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Monei\MoneiPayment\ViewModel\Order\PaymentInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for Order PaymentInfo ViewModel.
 *
 * @license  https://opensource.org/license/mit/ MIT License
 * @link     https://monei.com/
 */
class PaymentInfoTest extends TestCase
{
    /**
     * PaymentInfo instance being tested
     *
     * @var PaymentInfo
     */
    private $_paymentInfo;

    /**
     * Mock of Registry
     *
     * @var Registry|MockObject
     */
    private $_registryMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_registryMock = $this->createMock(Registry::class);
        $this->_paymentInfo = new PaymentInfo($this->_registryMock);
    }

    /**
     * Test that PaymentInfo implements ArgumentInterface
     *
     * @return void
     */
    public function testImplementsArgumentInterface(): void
    {
        $this->assertInstanceOf(
            ArgumentInterface::class,
            $this->_paymentInfo
        );
    }

    /**
     * Test getOrder returns order from registry
     *
     * @return void
     */
    public function testGetOrderReturnsOrderFromRegistry(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);

        $this
            ->_registryMock
            ->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn($orderMock);

        $result = $this->_paymentInfo->getOrder();

        $this->assertSame($orderMock, $result);
    }

    /**
     * Test getOrder returns null when order is not in registry
     *
     * @return void
     */
    public function testGetOrderReturnsNullWhenNoOrderInRegistry(): void
    {
        $this
            ->_registryMock
            ->expects($this->once())
            ->method('registry')
            ->with('current_order')
            ->willReturn(null);

        $result = $this->_paymentInfo->getOrder();

        $this->assertNull($result);
    }
}

<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Observer;

use Magento\Catalog\Block\ShortcutButtons;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\LayoutInterface;
use Monei\MoneiPayment\Api\Config\MoneiExpressCheckoutConfigInterface;
use Monei\MoneiPayment\Block\Express\Shortcut;
use Monei\MoneiPayment\Observer\AddExpressButton;
use PHPUnit\Framework\TestCase;

/**
 * Test for AddExpressButton observer.
 */
class AddExpressButtonTest extends TestCase
{
    /**
     * @var MoneiExpressCheckoutConfigInterface
     */
    private $_configMock;

    /**
     * @var AddExpressButton
     */
    private $_observer;

    /**
     * @var ShortcutButtons
     */
    private $_containerMock;

    protected function setUp(): void
    {
        $this->_configMock = $this->createMock(MoneiExpressCheckoutConfigInterface::class);
        $this->_observer = new AddExpressButton($this->_configMock);
        $this->_containerMock = $this->createMock(ShortcutButtons::class);
    }

    private function makeObserver(bool $isProduct, bool $isCart): Observer
    {
        // setExpressLocation reaches the block through DataObject's magic setters.
        $shortcut = $this
            ->getMockBuilder(Shortcut::class)
            ->disableOriginalConstructor()
            ->addMethods(['setExpressLocation'])
            ->getMock();
        $shortcut->method('setExpressLocation')->willReturnSelf();

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($shortcut);
        $this->_containerMock->method('getLayout')->willReturn($layout);

        $event = new Event([
            'container' => $this->_containerMock,
            'is_catalog_product' => $isProduct,
            'is_shopping_cart' => $isCart,
        ]);

        return new Observer(['event' => $event]);
    }

    /**
     * The global flag off must add nothing anywhere, whatever the per-surface
     * settings say.
     *
     * @return void
     */
    public function testAddsNothingWhenExpressIsDisabled(): void
    {
        $this->_configMock->method('isEnabled')->willReturn(false);
        $this->_containerMock->expects($this->never())->method('addShortcut');

        $this->_observer->execute($this->makeObserver(false, true));
    }

    /**
     * A surface that is individually disabled gets no button.
     *
     * @return void
     */
    public function testAddsNothingWhenTheSurfaceIsDisabled(): void
    {
        $this->_configMock->method('isEnabled')->willReturn(true);
        $this->_configMock->method('isEnabledAt')->willReturn(false);
        $this->_containerMock->expects($this->never())->method('addShortcut');

        $this->_observer->execute($this->makeObserver(true, false));
    }

    /**
     * The surfaces are told apart by the event's two flags. Neither flag set
     * means the mini cart, which is the case most easily got wrong.
     *
     * @dataProvider surfaceProvider
     *
     * @param bool   $isProduct
     * @param bool   $isCart
     * @param string $expected
     *
     * @return void
     */
    public function testResolvesEachSurfaceFromTheEventFlags(
        bool $isProduct,
        bool $isCart,
        string $expected
    ): void {
        $this->_configMock->method('isEnabled')->willReturn(true);
        $this
            ->_configMock
            ->expects($this->once())
            ->method('isEnabledAt')
            ->with($expected)
            ->willReturn(true);
        $this->_containerMock->expects($this->once())->method('addShortcut');

        $this->_observer->execute($this->makeObserver($isProduct, $isCart));
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: string}>
     */
    public function surfaceProvider(): array
    {
        return [
            'product page' => [true, false, MoneiExpressCheckoutConfigInterface::LOCATION_PRODUCT],
            'cart page' => [false, true, MoneiExpressCheckoutConfigInterface::LOCATION_CART],
            'mini cart' => [false, false, MoneiExpressCheckoutConfigInterface::LOCATION_MINICART],
        ];
    }
}

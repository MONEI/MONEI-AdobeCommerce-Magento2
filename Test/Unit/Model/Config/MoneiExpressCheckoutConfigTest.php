<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiExpressCheckoutConfigInterface;
use Monei\MoneiPayment\Model\Config\MoneiExpressCheckoutConfig;
use PHPUnit\Framework\TestCase;

/**
 * Test for MoneiExpressCheckoutConfig.
 */
class MoneiExpressCheckoutConfigTest extends TestCase
{
    /**
     * @var MoneiExpressCheckoutConfig
     */
    private $_config;

    /**
     * @var ScopeConfigInterface
     */
    private $_scopeConfigMock;

    protected function setUp(): void
    {
        $this->_scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->_config = new MoneiExpressCheckoutConfig($this->_scopeConfigMock);
    }

    /**
     * Test isEnabled reads the global flag
     *
     * @return void
     */
    public function testIsEnabled(): void
    {
        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('isSetFlag')
            ->with(
                MoneiExpressCheckoutConfigInterface::IS_ENABLED,
                ScopeInterface::SCOPE_STORE,
                1
            )
            ->willReturn(true);

        $this->assertTrue($this->_config->isEnabled(1));
    }

    /**
     * A surface is only enabled when both the global flag and its own are set.
     *
     * @return void
     */
    public function testIsEnabledAtRequiresBothFlags(): void
    {
        $this
            ->_scopeConfigMock
            ->method('isSetFlag')
            ->willReturnMap([
                [MoneiExpressCheckoutConfigInterface::IS_ENABLED, ScopeInterface::SCOPE_STORE, 1, true],
                [MoneiExpressCheckoutConfigInterface::ENABLED_ON_CART, ScopeInterface::SCOPE_STORE, 1, true],
                [MoneiExpressCheckoutConfigInterface::ENABLED_ON_PRODUCT, ScopeInterface::SCOPE_STORE, 1, false],
            ]);

        $this->assertTrue(
            $this->_config->isEnabledAt(MoneiExpressCheckoutConfigInterface::LOCATION_CART, 1)
        );
        $this->assertFalse(
            $this->_config->isEnabledAt(MoneiExpressCheckoutConfigInterface::LOCATION_PRODUCT, 1)
        );
    }

    /**
     * The global flag being off must beat any per-surface setting, or disabling
     * express would leave buttons on surfaces that were individually enabled.
     *
     * @return void
     */
    public function testGlobalFlagOffDisablesEverySurface(): void
    {
        $this
            ->_scopeConfigMock
            ->method('isSetFlag')
            ->willReturnMap([
                [MoneiExpressCheckoutConfigInterface::IS_ENABLED, ScopeInterface::SCOPE_STORE, 1, false],
                [MoneiExpressCheckoutConfigInterface::ENABLED_ON_CART, ScopeInterface::SCOPE_STORE, 1, true],
            ]);

        $this->assertFalse(
            $this->_config->isEnabledAt(MoneiExpressCheckoutConfigInterface::LOCATION_CART, 1)
        );
    }

    /**
     * An unknown surface must be treated as disabled: a typo in a caller must not
     * put a payment button somewhere nobody chose.
     *
     * @return void
     */
    public function testUnknownLocationIsDisabled(): void
    {
        $this
            ->_scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(true);

        $this->assertFalse($this->_config->isEnabledAt('somewhere-else', 1));
    }

    /**
     * Test getJsonStyle decodes stored JSON
     *
     * @return void
     */
    public function testGetJsonStyle(): void
    {
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->willReturn('{"height":"45px"}');

        $this->assertSame(['height' => '45px'], $this->_config->getJsonStyle(1));
    }

    /**
     * Malformed JSON must not fatal the checkout; it degrades to no styling.
     *
     * @return void
     */
    public function testGetJsonStyleReturnsEmptyArrayOnMalformedJson(): void
    {
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->willReturn('{not valid json');

        $this->assertSame([], $this->_config->getJsonStyle(1));
    }

    /**
     * "45" is valid JSON but not a style object. Returning it would violate the
     * array return type and fatal while Magento builds the checkout config.
     *
     * @return void
     */
    public function testGetJsonStyleRejectsAScalarJsonValue(): void
    {
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->willReturn('"45"');

        $this->assertSame([], $this->_config->getJsonStyle(1));
    }
}

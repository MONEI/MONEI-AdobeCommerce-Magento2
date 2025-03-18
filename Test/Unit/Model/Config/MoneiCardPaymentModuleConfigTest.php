<?php

/**
 * Test case for MoneiCardPaymentModuleConfig.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\MoneiCardPaymentModuleConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for MoneiCardPaymentModuleConfig.
 */
class MoneiCardPaymentModuleConfigTest extends TestCase
{
    /**
     * @var MoneiCardPaymentModuleConfig
     */
    private $_config;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $_scopeConfigMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->_config = new MoneiCardPaymentModuleConfig($this->_scopeConfigMock);
    }

    /**
     * Test isEnabled method
     *
     * @return void
     */
    public function testIsEnabled(): void
    {
        $storeId = 1;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::IS_PAYMENT_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn('1');

        $result = $this->_config->isEnabled($storeId);
        $this->assertTrue($result);
    }

    /**
     * Test getTitle method
     *
     * @return void
     */
    public function testGetTitle(): void
    {
        $storeId = 1;
        $title = 'Credit Card';

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::TITLE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($title);

        $result = $this->_config->getTitle($storeId);
        $this->assertEquals($title, $result);
    }

    /**
     * Test isEnabledTokenization method
     *
     * @return void
     */
    public function testIsEnabledTokenization(): void
    {
        $storeId = 1;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::IS_ENABLED_TOKENIZATION,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn('1');

        $result = $this->_config->isEnabledTokenization($storeId);
        $this->assertTrue($result);
    }

    /**
     * Test isAllowSpecific method
     *
     * @return void
     */
    public function testIsAllowSpecific(): void
    {
        $storeId = 1;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::ALLOW_SPECIFIC,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn('1');

        $result = $this->_config->isAllowSpecific($storeId);
        $this->assertTrue($result);
    }

    /**
     * Test getSpecificCountries method
     *
     * @return void
     */
    public function testGetSpecificCountries(): void
    {
        $storeId = 1;
        $countries = 'ES,PT,FR';

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::SPECIFIC_COUNTRIES,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($countries);

        $result = $this->_config->getSpecificCountries($storeId);
        $this->assertEquals($countries, $result);
    }

    /**
     * Test getSortOrder method
     *
     * @return void
     */
    public function testGetSortOrder(): void
    {
        $storeId = 1;
        $sortOrder = 100;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::SORT_ORDER,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn((string) $sortOrder);

        $result = $this->_config->getSortOrder($storeId);
        $this->assertEquals($sortOrder, $result);
    }

    /**
     * Test getJsonStyle method with valid JSON
     *
     * @return void
     */
    public function testGetJsonStyleWithValidJson(): void
    {
        $storeId = 1;
        $jsonStyle = '{"color":"#000000","fontFamily":"Arial"}';
        $expectedArray = [
            'color' => '#000000',
            'fontFamily' => 'Arial'
        ];

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::JSON_STYLE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($jsonStyle);

        $result = $this->_config->getJsonStyle($storeId);
        $this->assertEquals($expectedArray, $result);
    }

    /**
     * Test getJsonStyle method with empty JSON
     *
     * @return void
     */
    public function testGetJsonStyleWithEmptyJson(): void
    {
        $storeId = 1;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiCardPaymentModuleConfigInterface::JSON_STYLE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn('');

        $result = $this->_config->getJsonStyle($storeId);
        $this->assertEquals([], $result);
    }
}

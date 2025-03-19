<?php

/**
 * Test case for MoneiMultibancoRedirectPaymentModuleConfig.
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
use Monei\MoneiPayment\Api\Config\MoneiMultibancoRedirectPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\MoneiMultibancoRedirectPaymentModuleConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for MoneiMultibancoRedirectPaymentModuleConfig.
 */
class MoneiMultibancoRedirectPaymentModuleConfigTest extends TestCase
{
    /**
     * @var MoneiMultibancoRedirectPaymentModuleConfig
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
        $this->_config = new MoneiMultibancoRedirectPaymentModuleConfig($this->_scopeConfigMock);
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
                MoneiMultibancoRedirectPaymentModuleConfigInterface::IS_PAYMENT_ENABLED,
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
        $title = 'Multibanco';

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiMultibancoRedirectPaymentModuleConfigInterface::TITLE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($title);

        $result = $this->_config->getTitle($storeId);
        $this->assertEquals($title, $result);
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
                MoneiMultibancoRedirectPaymentModuleConfigInterface::ALLOW_SPECIFIC,
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
        $countries = 'PT';

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiMultibancoRedirectPaymentModuleConfigInterface::SPECIFIC_COUNTRIES,
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
        $sortOrder = 200;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiMultibancoRedirectPaymentModuleConfigInterface::SORT_ORDER,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn((string) $sortOrder);

        $result = $this->_config->getSortOrder($storeId);
        $this->assertEquals($sortOrder, $result);
    }
}

<?php

/**
 * Test case for MoneiPaymentModuleConfig.
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
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\Config\MoneiPaymentModuleConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for MoneiPaymentModuleConfig.
 */
class MoneiPaymentModuleConfigTest extends TestCase
{
    /**
     * @var MoneiPaymentModuleConfig
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
        $this->_config = new MoneiPaymentModuleConfig($this->_scopeConfigMock);
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
                MoneiPaymentModuleConfigInterface::IS_PAYMENT_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn('1');

        $result = $this->_config->isEnabled($storeId);
        $this->assertTrue($result);
    }

    /**
     * Test getMode method
     *
     * @return void
     */
    public function testGetMode(): void
    {
        $storeId = 1;
        $mode = Mode::MODE_TEST;

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                MoneiPaymentModuleConfigInterface::MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn((string) $mode);

        $result = $this->_config->getMode($storeId);
        $this->assertEquals($mode, $result);
    }

    /**
     * Test getUrl method in test mode
     *
     * @return void
     */
    public function testGetUrlInTestMode(): void
    {
        $storeId = 1;
        $testUrl = 'https://test-api.monei.com/v1';

        // Setup the return map for multiple getValue calls
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    MoneiPaymentModuleConfigInterface::MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    (string) Mode::MODE_TEST
                ],
                [
                    MoneiPaymentModuleConfigInterface::TEST_URL,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $testUrl
                ]
            ]));

        $result = $this->_config->getUrl($storeId);
        $this->assertEquals($testUrl, $result);
    }

    /**
     * Test getUrl method in production mode
     *
     * @return void
     */
    public function testGetUrlInProductionMode(): void
    {
        $storeId = 1;
        $productionUrl = 'https://api.monei.com/v1';

        // Setup the return map for multiple getValue calls
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    MoneiPaymentModuleConfigInterface::MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    (string) Mode::MODE_PRODUCTION
                ],
                [
                    MoneiPaymentModuleConfigInterface::PRODUCTION_URL,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $productionUrl
                ]
            ]));

        $result = $this->_config->getUrl($storeId);
        $this->assertEquals($productionUrl, $result);
    }

    /**
     * Test getAccountId method in test mode
     *
     * @return void
     */
    public function testGetAccountIdInTestMode(): void
    {
        $storeId = 1;
        $testAccountId = 'test_account_123';

        // Setup the return map for multiple getValue calls
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    MoneiPaymentModuleConfigInterface::MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    (string) Mode::MODE_TEST
                ],
                [
                    MoneiPaymentModuleConfigInterface::TEST_ACCOUNT_ID,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $testAccountId
                ]
            ]));

        $result = $this->_config->getAccountId($storeId);
        $this->assertEquals($testAccountId, $result);
    }

    /**
     * Test getAccountId method in production mode
     *
     * @return void
     */
    public function testGetAccountIdInProductionMode(): void
    {
        $storeId = 1;
        $productionAccountId = 'prod_account_456';

        // Setup the return map for multiple getValue calls
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    MoneiPaymentModuleConfigInterface::MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    (string) Mode::MODE_PRODUCTION
                ],
                [
                    MoneiPaymentModuleConfigInterface::PRODUCTION_ACCOUNT_ID,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $productionAccountId
                ]
            ]));

        $result = $this->_config->getAccountId($storeId);
        $this->assertEquals($productionAccountId, $result);
    }

    /**
     * Test getApiKey method in test mode
     *
     * @return void
     */
    public function testGetApiKeyInTestMode(): void
    {
        $storeId = 1;
        $testApiKey = 'test_api_key_xyz';

        // Setup the return map for multiple getValue calls
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    MoneiPaymentModuleConfigInterface::MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    (string) Mode::MODE_TEST
                ],
                [
                    MoneiPaymentModuleConfigInterface::TEST_API_KEY,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $testApiKey
                ]
            ]));

        $result = $this->_config->getApiKey($storeId);
        $this->assertEquals($testApiKey, $result);
    }

    /**
     * Test getApiKey method in production mode
     *
     * @return void
     */
    public function testGetApiKeyInProductionMode(): void
    {
        $storeId = 1;
        $productionApiKey = 'prod_api_key_abc';

        // Setup the return map for multiple getValue calls
        $this
            ->_scopeConfigMock
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    MoneiPaymentModuleConfigInterface::MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    (string) Mode::MODE_PRODUCTION
                ],
                [
                    MoneiPaymentModuleConfigInterface::PRODUCTION_API_KEY,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $productionApiKey
                ]
            ]));

        $result = $this->_config->getApiKey($storeId);
        $this->assertEquals($productionApiKey, $result);
    }

    /**
     * Test getLanguage method with supported locale
     *
     * @return void
     */
    public function testGetLanguageWithSupportedLocale(): void
    {
        $storeId = 1;
        $localeCode = 'es_ES';
        $expectedLanguage = 'es';

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($localeCode);

        $result = $this->_config->getLanguage($storeId);
        $this->assertEquals($expectedLanguage, $result);
    }

    /**
     * Test getLanguage method with unsupported locale
     *
     * @return void
     */
    public function testGetLanguageWithUnsupportedLocale(): void
    {
        $storeId = 1;
        $localeCode = 'xx_XX';  // Unsupported locale
        $expectedLanguage = 'en';  // Default to English

        $this
            ->_scopeConfigMock
            ->expects($this->once())
            ->method('getValue')
            ->with(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($localeCode);

        $result = $this->_config->getLanguage($storeId);
        $this->assertEquals($expectedLanguage, $result);
    }
}

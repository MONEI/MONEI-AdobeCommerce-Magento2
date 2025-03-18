<?php

/**
 * Test case for AllMoneiPaymentModuleConfig.
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

use Monei\MoneiPayment\Api\Config\MoneiBizumPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaypalPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\AllMoneiPaymentModuleConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for AllMoneiPaymentModuleConfig.
 */
class AllMoneiPaymentModuleConfigTest extends TestCase
{
    /**
     * @var AllMoneiPaymentModuleConfig
     */
    private $_config;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $_moneiPaymentModuleConfigMock;

    /**
     * @var MoneiCardPaymentModuleConfigInterface|MockObject
     */
    private $_moneiCardPaymentModuleConfigMock;

    /**
     * @var MoneiBizumPaymentModuleConfigInterface|MockObject
     */
    private $_moneiBizumPaymentModuleConfigMock;

    /**
     * @var MoneiGoogleApplePaymentModuleConfigInterface|MockObject
     */
    private $_moneiGoogleApplePaymentModuleConfigMock;

    /**
     * @var MoneiPaypalPaymentModuleConfigInterface|MockObject
     */
    private $_moneiPaypalPaymentModuleConfigMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_moneiPaymentModuleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->_moneiCardPaymentModuleConfigMock = $this->createMock(MoneiCardPaymentModuleConfigInterface::class);
        $this->_moneiBizumPaymentModuleConfigMock = $this->createMock(MoneiBizumPaymentModuleConfigInterface::class);
        $this->_moneiGoogleApplePaymentModuleConfigMock = $this->createMock(MoneiGoogleApplePaymentModuleConfigInterface::class);
        $this->_moneiPaypalPaymentModuleConfigMock = $this->createMock(MoneiPaypalPaymentModuleConfigInterface::class);

        $this->_config = new AllMoneiPaymentModuleConfig(
            $this->_moneiPaymentModuleConfigMock,
            $this->_moneiCardPaymentModuleConfigMock,
            $this->_moneiBizumPaymentModuleConfigMock,
            $this->_moneiGoogleApplePaymentModuleConfigMock,
            $this->_moneiPaypalPaymentModuleConfigMock
        );
    }

    /**
     * Test isAnyPaymentEnabled when all payments are disabled
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithNoneEnabled(): void
    {
        $this->_moneiPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiCardPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiBizumPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiGoogleApplePaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiPaypalPaymentModuleConfigMock->method('isEnabled')->willReturn(false);

        $result = $this->_config->isAnyPaymentEnabled();
        $this->assertFalse($result);
    }

    /**
     * Test isAnyPaymentEnabled when main Monei payment is enabled
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithMainPaymentEnabled(): void
    {
        $this->_moneiPaymentModuleConfigMock->method('isEnabled')->willReturn(true);
        $this->_moneiCardPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiBizumPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiGoogleApplePaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiPaypalPaymentModuleConfigMock->method('isEnabled')->willReturn(false);

        $result = $this->_config->isAnyPaymentEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isAnyPaymentEnabled when card payment is enabled
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithCardPaymentEnabled(): void
    {
        $this->_moneiPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiCardPaymentModuleConfigMock->method('isEnabled')->willReturn(true);
        $this->_moneiBizumPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiGoogleApplePaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiPaypalPaymentModuleConfigMock->method('isEnabled')->willReturn(false);

        $result = $this->_config->isAnyPaymentEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isAnyPaymentEnabled when Bizum payment is enabled
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithBizumPaymentEnabled(): void
    {
        $this->_moneiPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiCardPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiBizumPaymentModuleConfigMock->method('isEnabled')->willReturn(true);
        $this->_moneiGoogleApplePaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiPaypalPaymentModuleConfigMock->method('isEnabled')->willReturn(false);

        $result = $this->_config->isAnyPaymentEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isAnyPaymentEnabled when Google/Apple Pay payment is enabled
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithGoogleApplePaymentEnabled(): void
    {
        $this->_moneiPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiCardPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiBizumPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiGoogleApplePaymentModuleConfigMock->method('isEnabled')->willReturn(true);
        $this->_moneiPaypalPaymentModuleConfigMock->method('isEnabled')->willReturn(false);

        $result = $this->_config->isAnyPaymentEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isAnyPaymentEnabled when PayPal payment is enabled
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithPaypalPaymentEnabled(): void
    {
        $this->_moneiPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiCardPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiBizumPaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiGoogleApplePaymentModuleConfigMock->method('isEnabled')->willReturn(false);
        $this->_moneiPaypalPaymentModuleConfigMock->method('isEnabled')->willReturn(true);

        $result = $this->_config->isAnyPaymentEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isAnyPaymentEnabled with store ID parameter
     *
     * @return void
     */
    public function testIsAnyPaymentEnabledWithStoreId(): void
    {
        $storeId = 1;

        $this
            ->_moneiPaymentModuleConfigMock
            ->expects($this->once())
            ->method('isEnabled')
            ->with($storeId)
            ->willReturn(false);
        $this
            ->_moneiCardPaymentModuleConfigMock
            ->expects($this->once())
            ->method('isEnabled')
            ->with($storeId)
            ->willReturn(false);
        $this
            ->_moneiBizumPaymentModuleConfigMock
            ->expects($this->once())
            ->method('isEnabled')
            ->with($storeId)
            ->willReturn(true);
        $this
            ->_moneiGoogleApplePaymentModuleConfigMock
            ->expects($this->never())
            ->method('isEnabled');
        $this
            ->_moneiPaypalPaymentModuleConfigMock
            ->expects($this->never())
            ->method('isEnabled');

        $result = $this->_config->isAnyPaymentEnabled($storeId);
        $this->assertTrue($result);
    }
}

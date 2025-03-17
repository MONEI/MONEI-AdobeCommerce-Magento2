<?php

/**
 * Test for PaymentMethod helper
 *
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Helper\PaymentMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for PaymentMethod helper
 */
class PaymentMethodTest extends TestCase
{
    /**
     * PaymentMethod helper instance
     *
     * @var PaymentMethod
     */
    private $_paymentMethodHelper;

    /**
     * Request mock object
     *
     * @var MockObject|RequestInterface
     */
    private $_requestMock;

    /**
     * Asset repository mock object
     *
     * @var MockObject|Repository
     */
    private $_assetRepoMock;

    /**
     * Scope config mock object
     *
     * @var MockObject|ScopeConfigInterface
     */
    private $_scopeConfigMock;

    /**
     * Store manager mock object
     *
     * @var MockObject|StoreManagerInterface
     */
    private $_storeManagerMock;

    /**
     * Theme provider mock object
     *
     * @var MockObject|ThemeProviderInterface
     */
    private $_themeProviderMock;

    /**
     * App emulation mock object
     *
     * @var MockObject|Emulation
     */
    private $_appEmulationMock;

    /**
     * Store mock object
     *
     * @var MockObject|StoreInterface
     */
    private $_storeMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_requestMock = $this->createMock(RequestInterface::class);
        $this->_assetRepoMock = $this->createMock(Repository::class);
        $this->_scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->_storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->_themeProviderMock = $this->createMock(ThemeProviderInterface::class);
        $this->_appEmulationMock = $this->createMock(Emulation::class);
        $this->_storeMock = $this->createMock(StoreInterface::class);

        // Configure store manager mock
        $this
            ->_storeManagerMock
            ->expects($this->any())
            ->method('getStore')
            ->willReturn($this->_storeMock);

        $this
            ->_storeMock
            ->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        // Configure request mock
        $this
            ->_requestMock
            ->expects($this->any())
            ->method('isSecure')
            ->willReturn(false);

        $this->_paymentMethodHelper = new PaymentMethod(
            $this->_requestMock,
            $this->_assetRepoMock,
            $this->_scopeConfigMock,
            $this->_storeManagerMock,
            $this->_themeProviderMock,
            $this->_appEmulationMock
        );
    }

    /**
     * Test for getCardLabel method with last4 digits
     *
     * @return void
     */
    public function testGetCardLabelWithLast4(): void
    {
        $card = new \stdClass();
        $card->last4 = '1234';
        $card->brand = 'visa';

        $result = $this->_paymentMethodHelper->getCardLabel($card);

        $this->assertEquals('•••• 1234', $result);
    }
}

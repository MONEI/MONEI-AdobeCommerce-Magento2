<?php

/**
 * Copyright © Monei. All rights reserved.
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Model\ApplePayDomainRegister200Response;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\VerifyApplePayDomainInterface;
use Monei\MoneiPayment\Observer\ConfigChangeObserver;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigChangeObserverTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var MoneiGoogleApplePaymentModuleConfigInterface|MockObject
     */
    private $googleAppleConfigMock;

    /**
     * @var VerifyApplePayDomainInterface|MockObject
     */
    private $verifyApplePayDomainMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var Observer|MockObject
     */
    private $observerMock;

    /**
     * @var Event|MockObject
     */
    private $eventMock;

    /**
     * @var StoreInterface|MockObject
     */
    private $storeMock;

    /**
     * @var ConfigChangeObserver
     */
    private $observer;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $this->googleAppleConfigMock = $this->getMockForAbstractClass(
            MoneiGoogleApplePaymentModuleConfigInterface::class
        );
        $this->verifyApplePayDomainMock = $this->getMockForAbstractClass(
            VerifyApplePayDomainInterface::class
        );
        $this->storeManagerMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->observerMock = $this->createMock(Observer::class);
        $this->eventMock = $this
            ->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->setMethods(['getChangedPaths'])
            ->getMock();
        $this->storeMock = $this->getMockForAbstractClass(StoreInterface::class);

        $this->observer = new ConfigChangeObserver(
            $this->scopeConfigMock,
            $this->googleAppleConfigMock,
            $this->verifyApplePayDomainMock,
            $this->storeManagerMock,
            $this->loggerMock
        );
    }

    public function testExecuteWithNoMoneiConfigChange(): void
    {
        $changedPaths = ['payment/other_payment/active', 'general/store_information/name'];

        $this
            ->observerMock
            ->expects($this->once())
            ->method('getEvent')
            ->willReturn($this->eventMock);

        $this
            ->eventMock
            ->expects($this->once())
            ->method('getChangedPaths')
            ->willReturn($changedPaths);

        $this
            ->googleAppleConfigMock
            ->expects($this->never())
            ->method('isEnabled');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteWithMoneiConfigChangeButApplePayDisabled(): void
    {
        $changedPaths = ['payment/monei_card/active', 'payment/other_payment/active'];

        $this
            ->observerMock
            ->expects($this->once())
            ->method('getEvent')
            ->willReturn($this->eventMock);

        $this
            ->eventMock
            ->expects($this->once())
            ->method('getChangedPaths')
            ->willReturn($changedPaths);

        $this
            ->googleAppleConfigMock
            ->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this
            ->verifyApplePayDomainMock
            ->expects($this->never())
            ->method('execute');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteWithMoneiConfigChangeAndApplePayEnabled(): void
    {
        $changedPaths = ['payment/monei_card/active', 'payment/monei_google_apple/active'];
        $testDomain = 'test-store.com';
        $testBaseUrl = 'https://' . $testDomain . '/';

        $applePayResponse = $this->createMock(ApplePayDomainRegister200Response::class);

        $this
            ->observerMock
            ->expects($this->once())
            ->method('getEvent')
            ->willReturn($this->eventMock);

        $this
            ->eventMock
            ->expects($this->once())
            ->method('getChangedPaths')
            ->willReturn($changedPaths);

        $this
            ->googleAppleConfigMock
            ->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this
            ->storeManagerMock
            ->expects($this->once())
            ->method('getStore')
            ->willReturn($this->storeMock);

        $this
            ->storeMock
            ->expects($this->once())
            ->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_WEB)
            ->willReturn($testBaseUrl);

        $this
            ->verifyApplePayDomainMock
            ->expects($this->once())
            ->method('execute')
            ->with($testDomain)
            ->willReturn($applePayResponse);

        $this
            ->loggerMock
            ->expects($this->exactly(2))
            ->method('info');

        $this->observer->execute($this->observerMock);
    }
}

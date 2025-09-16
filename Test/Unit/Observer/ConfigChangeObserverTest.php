<?php

namespace Monei\MoneiPayment\Test\Unit\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Event;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Model\InlineObject;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\VerifyApplePayDomainInterface;
use Monei\MoneiPayment\Observer\ConfigChangeObserver;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigChangeObserverTest extends TestCase
{
    /**
     * @var ConfigChangeObserver
     */
    private ConfigChangeObserver $observer;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface $scopeConfigMock;

    /**
     * @var MoneiGoogleApplePaymentModuleConfigInterface|MockObject
     */
    private MoneiGoogleApplePaymentModuleConfigInterface $googleAppleConfigMock;

    /**
     * @var VerifyApplePayDomainInterface|MockObject
     */
    private VerifyApplePayDomainInterface $verifyApplePayDomainMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private StoreManagerInterface $storeManagerMock;

    /**
     * @var Logger|MockObject
     */
    private Logger $loggerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $apiClientMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private MoneiPaymentModuleConfigInterface $moneiConfigMock;

    /**
     * @var Observer|MockObject
     */
    private Observer $eventObserverMock;

    /**
     * @var Store|MockObject
     */
    private Store $storeMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->googleAppleConfigMock = $this->createMock(MoneiGoogleApplePaymentModuleConfigInterface::class);
        $this->verifyApplePayDomainMock = $this->createMock(VerifyApplePayDomainInterface::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->moneiConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->eventObserverMock = $this->createMock(Observer::class);
        $this->storeMock = $this->createMock(Store::class);

        $this->observer = new ConfigChangeObserver(
            $this->scopeConfigMock,
            $this->googleAppleConfigMock,
            $this->verifyApplePayDomainMock,
            $this->storeManagerMock,
            $this->loggerMock,
            $this->apiClientMock,
            $this->moneiConfigMock
        );
    }

    /**
     * Test that the observer properly handles config changes and verifies Apple Pay domain
     */
    public function testExecuteResetsSDKAndVerifiesDomain(): void
    {
        // Domain to verify
        $domain = 'example.com';
        $storeId = 1;
        $baseUrl = 'https://' . $domain;

        // Create event with changed paths data
        $eventMock = $this
            ->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getChangedPaths'])
            ->getMock();
        $eventMock
            ->method('getChangedPaths')
            ->willReturn(['payment/monei_card/active', 'payment/monei_googlepay/active']);

        // Configure observer mock
        $this->eventObserverMock->method('getEvent')->willReturn($eventMock);

        // Mock Google/Apple Pay config being enabled
        $this->googleAppleConfigMock->method('isEnabled')->willReturn(true);

        // Mock store configuration
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB)->willReturn($baseUrl);
        $this->storeMock->method('getId')->willReturn($storeId);

        // Mock scope config values
        $this
            ->scopeConfigMock
            ->method('getValue')
            ->with(
                $this->equalTo(MoneiPaymentModuleConfigInterface::MODE),
                $this->equalTo(ScopeInterface::SCOPE_STORE)
            )
            ->willReturn(0);  // 0 = Production mode

        // Response from verification
        $responseMock = $this
            ->getMockBuilder(InlineObject::class)
            ->disableOriginalConstructor()
            ->addMethods(['getStatus', 'getDomainName'])
            ->getMock();
        $responseMock->method('getStatus')->willReturn('verified');
        $responseMock->method('getDomainName')->willReturn($domain);

        // Verify API client is reset and domain verification is called
        $this
            ->apiClientMock
            ->expects($this->once())
            ->method('resetSdkInstance')
            ->with($this->equalTo($storeId));

        $this
            ->verifyApplePayDomainMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($domain), $this->equalTo($storeId))
            ->willReturn($responseMock);

        // Execute the observer
        $this->observer->execute($this->eventObserverMock);
    }

    /**
     * Test observer skips verification when Apple Pay is disabled
     */
    public function testSkipsVerificationWhenApplePayDisabled(): void
    {
        // Create event with changed paths data
        $eventMock = $this
            ->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getChangedPaths'])
            ->getMock();
        $eventMock
            ->method('getChangedPaths')
            ->willReturn(['payment/monei_card/active']);

        // Configure observer mock
        $this->eventObserverMock->method('getEvent')->willReturn($eventMock);

        // Mock Google/Apple Pay config being disabled
        $this->googleAppleConfigMock->method('isEnabled')->willReturn(false);

        // Verify verification is not called
        $this
            ->verifyApplePayDomainMock
            ->expects($this->never())
            ->method('execute');

        // Execute the observer
        $this->observer->execute($this->eventObserverMock);
    }

    /**
     * Test observer handles exceptions properly
     */
    public function testHandlesExceptionsGracefully(): void
    {
        // Domain to verify
        $domain = 'example.com';
        $baseUrl = 'https://' . $domain;

        // Create event with changed paths data
        $eventMock = $this
            ->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getChangedPaths'])
            ->getMock();
        $eventMock
            ->method('getChangedPaths')
            ->willReturn(['payment/monei_applepay/active']);

        // Configure observer mock
        $this->eventObserverMock->method('getEvent')->willReturn($eventMock);

        // Mock Google/Apple Pay config being enabled
        $this->googleAppleConfigMock->method('isEnabled')->willReturn(true);

        // Mock store configuration
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB)->willReturn($baseUrl);

        // Mock verify domain to throw an exception
        $this
            ->verifyApplePayDomainMock
            ->method('execute')
            ->willThrowException(new LocalizedException(__('Domain verification failed')));

        // Logger should record the error
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error during automatic domain verification'));

        // Execute the observer - it should not throw the exception
        $this->observer->execute($this->eventObserverMock);
    }
}

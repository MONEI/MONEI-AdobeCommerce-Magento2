<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Monei\MoneiPayment\Controller\Adminhtml\Connect\Start;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Connect\Broker;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Service\Connect\MemoryFlagStore;
use PHPUnit\Framework\TestCase;

/**
 * The start route: the scope of the request in the state entry, and the frontend callback as redirect_uri.
 *
 * Every test names the bug it catches.
 */
class StartTest extends TestCase
{
    private const CALLBACK = 'https://shop.example/monei/payment/connect/';

    private string $location = '';

    /**
     * Bug: the start stores the state without the scope being edited (the callback then switches the default
     * scope), or sends the admin URL with its secret key/… as redirect_uri.
     */
    public function testStartStoresTheRequestScopeAndRedirectsWithTheFrontendCallback(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['website', null, '1'], ['store', null, '']]);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(5);
        $website = $this->createMock(Website::class);
        $website->method('getDefaultStore')->willReturn($store);
        $website->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->with(1)->willReturn($website);
        $url = $this->createMock(Url::class);
        $url->method('getUrl')
            ->with('monei/payment/connect', ['_scope' => 5, '_nosid' => true, '_secure' => true])
            ->willReturn(self::CALLBACK);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())->method('setUrl')->willReturnCallback(function (string $location) use ($redirect) {
            $this->location = $location;
            return $redirect;
        });
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);

        $flags = new MemoryFlagStore();
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(1700000000);
        $service = new ConnectService(
            $flags,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(WriterInterface::class),
            $this->createMock(TypeListInterface::class),
            $this->createMock(MoneiApiClient::class),
            new Broker(),
            $dateTime,
            $this->createMock(Logger::class)
        );

        (new Start($context, $service, new CallbackUrl($url, $storeManager)))->execute();

        parse_str((string) parse_url($this->location, PHP_URL_QUERY), $query);
        $this->assertSame(
            ['magento', self::CALLBACK, 'S256'],
            [$query['client_id'], $query['redirect_uri'], $query['code_challenge_method']]
        );
        $entry = $flags->flags[ConnectService::STATE_PREFIX . $query['state']];
        $this->assertSame(['websites', 1, self::CALLBACK], [$entry['scope'], $entry['scopeId'], $entry['redirectUri']]);
    }

    /**
     * Bug: a website parameter that names no website starts a connect of the default scope.
     */
    public function testAnUnknownScopeStoresNoState(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['website', null, 'nope'], ['store', null, '']]);
        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->never())->method('setUrl');
        $redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->createMock(\Magento\Framework\Message\ManagerInterface::class));
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException());
        $flags = new MemoryFlagStore();
        $service = new ConnectService(
            $flags,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(WriterInterface::class),
            $this->createMock(TypeListInterface::class),
            $this->createMock(MoneiApiClient::class),
            new Broker(),
            $this->createMock(DateTime::class),
            $this->createMock(Logger::class)
        );

        (new Start($context, $service, new CallbackUrl($this->createMock(Url::class), $storeManager)))->execute();

        $this->assertSame([], $flags->flags);
    }
}

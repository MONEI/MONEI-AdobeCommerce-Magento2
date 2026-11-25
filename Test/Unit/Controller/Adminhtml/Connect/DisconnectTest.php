<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Adminhtml\Connect;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Monei\MoneiPayment\Controller\Adminhtml\Connect\Disconnect;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use PHPUnit\Framework\TestCase;

/**
 * The disconnect route clears the scope being edited.
 *
 * Every test names the bug it catches.
 */
class DisconnectTest extends TestCase
{
    /**
     * Bug: a disconnect on a store view's page clears the default scope, so every other store loses its keys and
     * the store view keeps its own.
     */
    public function testDisconnectClearsTheScopeOfTheRequest(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['website', null, '1'], ['store', null, '3']]);
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->createMock(ManagerInterface::class));
        $service = $this->createMock(ConnectService::class);
        $service->expects($this->once())->method('disconnect')->with('stores', 3)
            ->willReturn(['cleared' => ['live'], 'locked' => []]);
        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn(1);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturn($website);
        $storeManager->method('getStore')->willReturn($store);

        (new Disconnect($context, $service, new CallbackUrl($this->createMock(Url::class), $storeManager)))->execute();
    }

    /**
     * Bug: a store parameter that names no store (or a store of another website) clears the default scope.
     */
    public function testAnUnknownScopeChangesNothing(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['website', null, ''], ['store', null, 'nope']]);
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->once())->method('addErrorMessage');
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);
        $service = $this->createMock(ConnectService::class);
        $service->expects($this->never())->method('disconnect');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException());

        (new Disconnect($context, $service, new CallbackUrl($this->createMock(Url::class), $storeManager)))->execute();
    }

    /**
     * Bug: a Disconnect that leaves a mode set by env.php, config.php or an environment variable says "MONEI
     * disconnected", while that key stays in use.
     */
    public function testALockedModeIsReportedNotCalledDisconnected(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['website', null, ''], ['store', null, '']]);
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->never())->method('addSuccessMessage');
        $messages->expects($this->once())->method('addWarningMessage')->with(
            'Live: The keys of this mode are set in env.php, config.php or an environment variable, so Connect with MONEI cannot change them.'
        );
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);
        $service = $this->createMock(ConnectService::class);
        $service->method('disconnect')->with('default', 0)->willReturn(['cleared' => [], 'locked' => ['live']]);

        (new Disconnect($context, $service, new CallbackUrl($this->createMock(Url::class), $this->createMock(StoreManagerInterface::class))))->execute();
    }
}

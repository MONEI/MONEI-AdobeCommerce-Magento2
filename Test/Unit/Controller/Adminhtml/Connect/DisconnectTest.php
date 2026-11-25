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
        $service->expects($this->once())->method('disconnect')->with('stores', 3);

        (new Disconnect($context, $service))->execute();
    }
}

<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Block\System\Config\Form\Field;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Escaper;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\User\Model\User;
use Monei\MoneiPayment\Block\System\Config\Form\Field\ConnectButton;
use Monei\MoneiPayment\Model\Config\Backend\CredentialValue;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use PHPUnit\Framework\TestCase;

/**
 * The "Connect with MONEI" row of the payment configuration.
 *
 * Every test names the bug it catches.
 */
class ConnectButtonTest extends TestCase
{
    private function render(string $callback, bool $dismissed, array $locked = []): string
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['website', null, null], ['store', null, '2']]);
        $escaper = $this->createMock(Escaper::class);
        foreach (['escapeHtml', 'escapeHtmlAttr', 'escapeUrl'] as $method) {
            $escaper->method($method)->willReturnCallback(function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES);
            });
        }
        $backendUrl = $this->createMock(UrlInterface::class);
        $backendUrl->method('getUrl')->willReturn('https://shop.example/admin/monei/connect/start/key/abc/');
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getEscaper')->willReturn($escaper);
        $context->method('getUrlBuilder')->willReturn($backendUrl);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(2)->willReturn($store);
        $frontendUrl = $this->createMock(Url::class);
        $frontendUrl->method('getUrl')->willReturn($callback);

        $service = $this->createMock(ConnectService::class);
        $service->method('apiKeys')->willReturn(['live' => 'pk_live_manual', 'test' => 'mk_test_0123456789abcdef']);
        $service->method('isNoticeDismissed')->with(9)->willReturn($dismissed);
        $service->method('lockedModes')->willReturn($locked);
        $service->method('connectedModes')->willReturn(['test']);
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->method('renderEventListenerAsTag')->willReturn('<script></script>');
        $secureRenderer->method('renderTag')->willReturnCallback(function ($tag, $attributes, $content) {
            return "<$tag>$content</$tag>";
        });
        $element = $this->createMock(AbstractElement::class);
        $element->method('getHtmlId')->willReturn('payment_monei_connect');
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(9);
        $sessionBuilder = $this->getMockBuilder(Session::class)->disableOriginalConstructor();
        $sessionBuilder = method_exists(Session::class, 'getUser')
            ? $sessionBuilder->onlyMethods(['getUser'])
            : $sessionBuilder->addMethods(['getUser']);
        $session = $sessionBuilder->getMock();
        $session->method('getUser')->willReturn($user);

        $block = new ConnectButton(
            $context,
            new CallbackUrl($frontendUrl, $storeManager),
            $service,
            $secureRenderer,
            $this->createMock(FormKey::class),
            $session
        );

        return $block->render($element);
    }

    /**
     * Bug: the button shows for a store whose callback URL the broker refuses for a reason the plugin can check,
     * so the merchant meets a broker error page; or it never shows; or the notice asks a store that cannot connect
     * to connect; or the manual key fields hide on a store that can only use them, or crowd a store that can
     * connect. The page also posts the fingerprint of each mode's key, or CredentialValue cannot tell a page loaded
     * before a connect. It never shows a scoped key in full.
     *
     * @dataProvider callbackUrls
     */
    public function testTheButtonShowsOnlyWhenTheBrokerTakesTheCallbackUrl(string $callback, bool $button): void
    {
        $html = $this->render($callback, false);

        $this->assertSame(
            [$button, !$button, $button, $button],
            [
                strpos($html, 'id="monei-connect-button"') !== false,
                strpos($html, 'Use manual keys. Connect with MONEI needs a public HTTPS address for this shop.') !== false,
                strpos($html, 'to stop storing your account API key') !== false,
                strpos($html, '#row_payment_monei_test_account_id,#row_payment_monei_test_api_key,'
                    . '#row_payment_monei_production_account_id,#row_payment_monei_production_api_key'
                    . '{display:none !important}') !== false
                    && strpos($html, 'Enter API keys manually') !== false,
            ]
        );
        foreach (['live' => 'pk_live_manual', 'test' => 'mk_test_0123456789abcdef'] as $name => $value) {
            $this->assertStringContainsString(
                'name="monei_loaded_keys[' . $name . ']" value="' . CredentialValue::fingerprint($value) . '"',
                $html
            );
        }
        $this->assertSame(
            [false, true],
            [strpos($html, 'mk_test_0123456789abcdef') !== false, strpos($html, 'Key in use (Test): ending in cdef') !== false]
        );
    }

    public function callbackUrls(): array
    {
        return [
            'two prefix segments' => ['https://shop.example/a/b/monei/payment/connect/', true],
            'three prefix segments' => ['https://shop.example/a/b/c/monei/payment/connect/', false],
        ];
    }

    /**
     * Bug: an admin who dismissed the invitation to connect sees it on every visit.
     */
    public function testAnAdminWhoDismissedTheNoticeDoesNotSeeIt(): void
    {
        $html = $this->render('https://shop.example/monei/payment/connect/', true);

        $this->assertSame(
            [false, true],
            [strpos($html, 'to stop storing your account API key') !== false, strpos($html, 'id="monei-connect-button"') !== false]
        );
    }

    /**
     * Bug: a scope whose keys env.php or an environment variable sets offers Connect and Disconnect, which write
     * values that never take effect.
     */
    public function testLockedKeysOfferNeitherConnectNorDisconnect(): void
    {
        $html = $this->render('https://shop.example/monei/payment/connect/', false, ['live', 'test']);

        $this->assertSame(
            [false, false, true],
            [
                strpos($html, 'id="monei-connect-button"') !== false,
                strpos($html, 'id="monei-disconnect-button"') !== false,
                strpos($html, 'are set in env.php or an environment variable') !== false,
            ]
        );
    }
}

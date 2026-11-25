<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Block\System\Config\Form\Field;

use Magento\Backend\Block\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Escaper;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
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
    /**
     * Bug: the button shows for a store whose callback URL the broker refuses for a reason the plugin can check,
     * so the merchant meets a broker error page; or it never shows; or the notice asks a store that cannot connect
     * to connect. The page also posts the fingerprint of each value in use, or CredentialValue cannot tell a page
     * loaded before a connect. It never shows a scoped key in full.
     *
     * @dataProvider callbackUrls
     */
    public function testTheButtonShowsOnlyWhenTheBrokerTakesTheCallbackUrl(string $callback, bool $button): void
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
        $service->method('mode')->willReturn('2');
        $secureRenderer = $this->createMock(SecureHtmlRenderer::class);
        $secureRenderer->method('renderEventListenerAsTag')->willReturn('<script></script>');
        $element = $this->createMock(AbstractElement::class);
        $element->method('getHtmlId')->willReturn('payment_monei_connect');

        $block = new ConnectButton(
            $context,
            new CallbackUrl($frontendUrl, $storeManager),
            $service,
            $secureRenderer,
            $this->createMock(FormKey::class)
        );
        $html = $block->render($element);

        $this->assertSame(
            [$button, !$button, $button],
            [
                strpos($html, 'id="monei-connect-button"') !== false,
                strpos($html, 'Use manual keys') !== false,
                strpos($html, 'to stop storing your account API key') !== false,
            ]
        );
        foreach (['live' => 'pk_live_manual', 'test' => 'mk_test_0123456789abcdef', 'mode' => '2'] as $name => $value) {
            $this->assertStringContainsString(
                'name="monei_loaded_keys[' . $name . ']" value="' . CredentialValue::fingerprint($value) . '"',
                $html
            );
        }
        $this->assertSame(
            [false, true],
            [strpos($html, 'mk_test_0123456789abcdef') !== false, strpos($html, '•••• cdef') !== false]
        );
    }

    public function callbackUrls(): array
    {
        return [
            'two prefix segments' => ['https://shop.example/a/b/monei/payment/connect/', true],
            'three prefix segments' => ['https://shop.example/a/b/c/monei/payment/connect/', false],
        ];
    }
}

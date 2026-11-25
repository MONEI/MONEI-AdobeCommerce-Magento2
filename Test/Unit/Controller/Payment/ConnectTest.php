<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Monei\MoneiPayment\Controller\Payment\Connect;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Connect\Broker;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use Monei\MoneiPayment\Service\Connect\LockedCredentials;
use Monei\MoneiPayment\Service\Connect\ResultPage;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Service\Connect\MemoryFlagStore;
use PHPUnit\Framework\TestCase;

/**
 * The frontend callback route of "Connect with MONEI" and its result page.
 *
 * Every test names the bug it catches.
 */
class ConnectTest extends TestCase
{
    private function resultPage(): ResultPage
    {
        $escaper = $this->createMock(Escaper::class);
        foreach (['escapeHtml', 'escapeHtmlAttr', 'escapeUrl'] as $method) {
            $escaper->method($method)->willReturnCallback(function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES);
            });
        }
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnMap([
            ['admin/url/use_custom', 'default', null, false],
            ['web/secure/use_in_adminhtml', 'default', null, true],
        ]);
        $config->method('getValue')->willReturnMap([['web/secure/base_url', 'default', null, 'https://shop.example/']]);
        $frontName = $this->createMock(FrontNameResolver::class);
        $frontName->method('getFrontName')->willReturn('backoffice');
        $locale = $this->createMock(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('en_US');

        return new ResultPage($escaper, $config, $frontName, $locale);
    }

    /**
     * Bug: a forged state gets a 200 page, or the page sends the code of its URL to another site in a Referer, or
     * shows anyone the admin URL that a custom admin path hides.
     */
    public function testAForgedStateGets400WithNoReferrerAndNoExchange(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['state', null, str_repeat('f', 43)],
            ['code', null, 'the-code'],
        ]);
        $headers = [];
        $codes = [];
        $raw = $this->createMock(Raw::class);
        $raw->method('setHeader')->willReturnCallback(function ($name, $value) use (&$headers, $raw) {
            $headers[$name] = $value;
            return $raw;
        });
        $raw->method('setHttpResponseCode')->willReturnCallback(function ($code) use (&$codes, $raw) {
            $codes[] = $code;
            return $raw;
        });
        $rawFactory = $this->createMock(RawFactory::class);
        $rawFactory->method('create')->willReturn($raw);
        $broker = $this->createMock(Broker::class);
        $broker->expects($this->never())->method('exchange');
        $service = new ConnectService(
            new MemoryFlagStore(),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(WriterInterface::class),
            $this->createMock(TypeListInterface::class),
            $this->createMock(MoneiApiClient::class),
            $broker,
            $this->createMock(DateTime::class),
            $this->createMock(Logger::class),
            $this->createMock(LockedCredentials::class)
        );

        $contents = '';
        $raw->method('setContents')->willReturnCallback(function ($html) use (&$contents, $raw) {
            $contents = $html;
            return $raw;
        });

        (new Connect($request, $rawFactory, $service, $this->resultPage(), $this->createMock(Logger::class)))->execute();

        $this->assertSame([400, false], [$codes[0] ?? null, strpos($contents, 'backoffice') !== false]);
        $this->assertSame(
            ['no-referrer', 'no-store, no-cache, must-revalidate, max-age=0'],
            [$headers['Referrer-Policy'] ?? null, $headers['Cache-Control'] ?? null]
        );
    }

    /**
     * Bug: the page drops the result of a mode, so the merchant does not know that Live failed; or its button links
     * to a front URL or an admin URL with a secret key that a front route cannot make; or it loads from another
     * host (a static URL can be on a CDN, so the logo is inline).
     */
    public function testThePageShowsOneLinePerModeAndLinksToTheAdmin(): void
    {
        $html = $this->resultPage()->render([
            'switched' => ['test' => []],
            'problems' => [['mode' => 'live', 'code' => 'not_approved']],
        ]);

        preg_match_all('#<li class="(line--\w+)">.*?<span>(.*?)</span></li>#', $html, $lines, PREG_SET_ORDER);
        $this->assertSame(
            [
                ['line--ok', 'Test: connected.'],
                ['line--problem', 'Live: Live keys come after MONEI approves your account.'],
            ],
            array_map(function ($line) {
                return [$line[1], $line[2]];
            }, $lines)
        );
        $this->assertStringContainsString('<a class="button" href="https://shop.example/backoffice/">', $html);
        preg_match_all('#(?:src|href)="([^"]+)"#', $html, $urls);
        $this->assertSame(
            [true, 'https://shop.example/backoffice/'],
            [strpos($urls[1][0], 'data:image/svg+xml;base64,') === 0, $urls[1][1]]
        );
        $this->assertCount(2, $urls[1]);
    }

    /**
     * Bug: an error before the state is checked (the flag table fails) shows anyone the admin URL that a custom
     * admin path hides.
     */
    public function testAFailureBeforeTheStateCheckShowsNoAdminLink(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturn('x');
        $contents = '';
        $raw = $this->createMock(Raw::class);
        $raw->method('setHeader')->willReturnSelf();
        $raw->method('setHttpResponseCode')->willReturnSelf();
        $raw->method('setContents')->willReturnCallback(function ($html) use (&$contents, $raw) {
            $contents = $html;
            return $raw;
        });
        $rawFactory = $this->createMock(RawFactory::class);
        $rawFactory->method('create')->willReturn($raw);
        $service = $this->createMock(ConnectService::class);
        $service->method('complete')->willThrowException(new \RuntimeException('flag table gone'));

        (new Connect($request, $rawFactory, $service, $this->resultPage(), $this->createMock(Logger::class)))->execute();

        $this->assertSame(
            [true, false],
            [strpos($contents, 'MONEI is not connected') !== false, strpos($contents, 'backoffice') !== false]
        );
    }
}

<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Monei\MoneiPayment\Controller\Payment\Connect;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Connect\Broker;
use Monei\MoneiPayment\Service\Connect\ConnectService;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Service\Connect\MemoryFlagStore;
use PHPUnit\Framework\TestCase;

/**
 * The frontend callback route of "Connect with MONEI".
 *
 * Every test names the bug it catches.
 */
class ConnectTest extends TestCase
{
    /**
     * Bug: a forged state gets a 200 page, or the page sends the code of its URL to another site in a Referer.
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
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
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
            $this->createMock(Logger::class)
        );

        (new Connect($request, $rawFactory, $service, $escaper, $this->createMock(Logger::class)))->execute();

        $this->assertSame([400], $codes);
        $this->assertSame('no-referrer', $headers['Referrer-Policy'] ?? null);
    }
}

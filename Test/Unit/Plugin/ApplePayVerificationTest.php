<?php

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\Dir;
use Monei\MoneiPayment\Plugin\ApplePayVerification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test case for ApplePayVerification plugin
 */
class ApplePayVerificationTest extends TestCase
{
    private const MONEI_APPLE_PAY_FILE_URL = 'https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/';

    /**
     * @var ApplePayVerification
     */
    private $plugin;

    /**
     * @var File|MockObject
     */
    private $fileMock;

    /**
     * @var Reader|MockObject
     */
    private $moduleReaderMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var ResponseHttp|MockObject
     */
    private $responseMock;

    /**
     * @var FrontControllerInterface|MockObject
     */
    private $frontControllerMock;

    /**
     * @var RequestHttp|MockObject
     */
    private $requestMock;

    /**
     * @var Curl|MockObject
     */
    private $curlMock;

    /**
     * @var ResultFactory|MockObject
     */
    private $resultFactoryMock;

    /**
     * @var Raw|MockObject
     */
    private $resultRawMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $eventManagerMock;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->fileMock = $this->createMock(File::class);
        $this->moduleReaderMock = $this->createMock(Reader::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->responseMock = $this->createMock(ResponseHttp::class);
        $this->frontControllerMock = $this->createMock(FrontControllerInterface::class);
        $this->requestMock = $this->createMock(RequestHttp::class);
        $this->curlMock = $this->createMock(Curl::class);
        $this->resultFactoryMock = $this->createMock(ResultFactory::class);
        $this->resultRawMock = $this->createMock(Raw::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);

        // Configure ResultFactory mock to return Raw result
        $this
            ->resultFactoryMock
            ->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->resultRawMock);

        // Configure ResultRaw mock to be chainable
        $this
            ->resultRawMock
            ->method('setHttpResponseCode')
            ->willReturnSelf();
        $this
            ->resultRawMock
            ->method('setHeader')
            ->willReturnSelf();
        $this
            ->resultRawMock
            ->method('setContents')
            ->willReturnSelf();

        $this->plugin = new ApplePayVerification(
            $this->fileMock,
            $this->moduleReaderMock,
            $this->loggerMock,
            $this->responseMock,
            $this->curlMock,
            $this->resultFactoryMock,
            $this->eventManagerMock
        );
    }

    /**
     * Test that non-Apple Pay verification requests are passed through
     */
    public function testRegularRequestsPassThrough(): void
    {
        // Setup request mock to return a regular path
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/catalog/product/view');

        // Execute the plugin
        $result = $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);

        // The plugin should not return anything for regular requests
        $this->assertNull($result);
    }

    /**
     * Test the Apple Pay verification file is fetched and served successfully
     */
    public function testApplePayVerificationFileIsServedWhenExists(): void
    {
        // Setup request mock to return the Apple Pay verification path
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        // Setup curl mock for successful response
        $this
            ->curlMock
            ->expects($this->once())
            ->method('get')
            ->with(self::MONEI_APPLE_PAY_FILE_URL);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(200);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('getBody')
            ->willReturn('Apple Pay verification file content');

        // Expect the event manager to be called
        $this
            ->eventManagerMock
            ->expects($this->once())
            ->method('dispatch')
            ->with('controller_action_predispatch');

        // Expect the result to be prepared
        $this
            ->resultRawMock
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(200)
            ->willReturnSelf();

        $this
            ->resultRawMock
            ->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'text/plain')
            ->willReturnSelf();

        $this
            ->resultRawMock
            ->expects($this->once())
            ->method('setContents')
            ->with('Apple Pay verification file content')
            ->willReturnSelf();

        // Execute the plugin
        $result = $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);

        // The plugin should return a ResultInterface
        $this->assertSame($this->resultRawMock, $result);
    }

    /**
     * Test handling when the Apple Pay verification file request fails
     */
    public function testHandlesRequestFailure(): void
    {
        // Setup request mock to return the Apple Pay verification path
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        // Setup curl mock for failed response
        $this
            ->curlMock
            ->expects($this->once())
            ->method('get')
            ->with(self::MONEI_APPLE_PAY_FILE_URL);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(404);

        // Expect an error to be logged
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ApplePay] Failed to fetch verification file'));

        // Expect error status code to be set on result
        $this
            ->resultRawMock
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(404)
            ->willReturnSelf();

        // Execute the plugin
        $result = $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);

        // The plugin should return a ResultInterface
        $this->assertSame($this->resultRawMock, $result);
    }

    /**
     * Test handling when an exception occurs during the request
     */
    public function testHandlesException(): void
    {
        // Setup request mock to return the Apple Pay verification path
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        // Setup curl mock to throw exception
        $this
            ->curlMock
            ->expects($this->once())
            ->method('get')
            ->with(self::MONEI_APPLE_PAY_FILE_URL)
            ->willThrowException(new \Exception('Network error'));

        // Expect an error to be logged
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('[ApplePay] Error serving verification file'));

        // Expect 500 status code to be set on result
        $this
            ->resultRawMock
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(500)
            ->willReturnSelf();

        // Execute the plugin
        $result = $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);

        // The plugin should return a ResultInterface
        $this->assertSame($this->resultRawMock, $result);
    }
}

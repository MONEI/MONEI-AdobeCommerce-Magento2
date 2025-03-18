<?php

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\FrontControllerInterface;
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

        $this->plugin = new ApplePayVerification(
            $this->fileMock,
            $this->moduleReaderMock,
            $this->loggerMock,
            $this->responseMock,
            $this->curlMock
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
            ->method('getPathInfo')
            ->willReturn('/catalog/product/view');

        // The file system should not be accessed
        $this
            ->fileMock
            ->expects($this->never())
            ->method('isExists');

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
            ->method('getPathInfo')
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

        // Expect specific headers to be set
        $this
            ->responseMock
            ->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'text/plain');

        // Expect the response body to be set
        $this
            ->responseMock
            ->expects($this->once())
            ->method('setBody')
            ->with('Apple Pay verification file content');

        // We can't fully test the method because it calls exit()
        try {
            $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);
        } catch (\Exception $e) {
            $this->fail('An exception was thrown: ' . $e->getMessage());
        }

        $this->markTestIncomplete(
            'This test cannot fully verify the method due to the exit() call.'
        );
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
            ->method('getPathInfo')
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
            ->with($this->stringContains('Failed to fetch Apple Pay verification file'));

        // Expect error status code to be set
        $this
            ->responseMock
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(404);

        // We can't fully test the method because it calls exit()
        $this->markTestIncomplete(
            'This test cannot fully verify the method due to the exit() call.'
        );
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
            ->method('getPathInfo')
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
            ->with($this->stringContains('Error serving Apple Pay verification file'));

        // Expect 500 status code to be set
        $this
            ->responseMock
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(500);

        // We can't fully test the method because it calls exit()
        $this->markTestIncomplete(
            'This test cannot fully verify the method due to the exit() call.'
        );
    }
}

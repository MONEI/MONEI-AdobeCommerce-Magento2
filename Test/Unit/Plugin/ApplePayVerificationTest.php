<?php

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Monei\MoneiPayment\Plugin\ApplePayVerification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test case for ApplePayVerification plugin
 */
class ApplePayVerificationTest extends TestCase
{
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

        $this->plugin = new ApplePayVerification(
            $this->fileMock,
            $this->moduleReaderMock,
            $this->loggerMock,
            $this->responseMock
        );
    }

    /**
     * Test that non-Apple Pay verification requests are passed through
     */
    public function testRegularRequestsPassThrough(): void
    {
        // Setup request mock to return a regular path
        $this->requestMock
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/catalog/product/view');

        // The file system should not be accessed
        $this->fileMock
            ->expects($this->never())
            ->method('isExists');

        // Execute the plugin
        $result = $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);

        // The plugin should not return anything for regular requests
        $this->assertNull($result);
    }

    /**
     * Test the Apple Pay verification file is served when it exists
     */
    public function testApplePayVerificationFileIsServedWhenExists(): void
    {
        // This test is limited because exit() will terminate PHPUnit
        // We can only test up to the point where the file is found and served

        // Setup request mock to return the Apple Pay verification path
        $this->requestMock
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        // Setup module reader to return a file path
        $filePath = '/path/to/apple-developer-merchantid-domain-association';
        $this->moduleReaderMock
            ->method('getModuleDir')
            ->with(Dir::MODULE_VIEW_DIR, 'Monei_MoneiPayment')
            ->willReturn('/path/to');

        // Setup file mock to indicate the file exists
        $this->fileMock
            ->method('isExists')
            ->with($filePath)
            ->willReturn(true);

        // Setup expectation that file content is retrieved
        $this->fileMock
            ->method('fileGetContents')
            ->with($filePath)
            ->willReturn('Apple Pay verification file content');
            
        // Expect specific headers to be set
        $this->responseMock
            ->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'text/plain');
            
        // Expect the response body to be set
        $this->responseMock
            ->expects($this->once())
            ->method('setBody')
            ->with('Apple Pay verification file content');

        // We can't fully test the method because it calls exit()
        // This test will pass if no exceptions are thrown
        try {
            $this->plugin->beforeDispatch($this->frontControllerMock, $this->requestMock);
        } catch (\Exception $e) {
            $this->fail('An exception was thrown: ' . $e->getMessage());
        }
        
        // Since we can't verify beyond the exit() call, this test is incomplete
        $this->markTestIncomplete(
            'This test cannot fully verify the method due to the exit() call.'
        );
    }
    
    /**
     * Test handling when the Apple Pay verification file is not found
     */
    public function testHandlesFileNotFound(): void
    {
        // Setup request mock to return the Apple Pay verification path
        $this->requestMock
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        // Setup module reader to return a file path
        $filePath = '/path/to/apple-developer-merchantid-domain-association';
        $this->moduleReaderMock
            ->method('getModuleDir')
            ->with(Dir::MODULE_VIEW_DIR, 'Monei_MoneiPayment')
            ->willReturn('/path/to');

        // Setup file mock to indicate the file does not exist
        $this->fileMock
            ->method('isExists')
            ->with($filePath)
            ->willReturn(false);
            
        // Expect an error to be logged
        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Apple Pay verification file not found'));
            
        // Expect 404 status code to be set
        $this->responseMock
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(404);

        // We can't fully test the method because it calls exit()
        $this->markTestIncomplete(
            'This test cannot fully verify the method due to the exit() call.'
        );
    }
}
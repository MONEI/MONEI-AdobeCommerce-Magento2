<?php

namespace Monei\MoneiPayment\Test\Unit\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Request\Http as RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Monei\MoneiPayment\Plugin\ApplePayVerification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Closure;

/**
 * Test case for ApplePayVerification plugin
 */
class ApplePayVerificationTest extends TestCase
{
    private const MONEI_APPLE_PAY_FILE_URL = 'https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/';
    private const PAYMENT_METHOD_CODE = 'monei_google_apple';

    /**
     * @var ApplePayVerification
     */
    private $plugin;

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
    private $subjectMock;

    /**
     * @var RequestHttp|MockObject
     */
    private $requestMock;

    /**
     * @var Curl|MockObject
     */
    private $curlMock;

    /**
     * @var PaymentHelper|MockObject
     */
    private $paymentHelperMock;

    /**
     * @var MethodInterface|MockObject
     */
    private $methodMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var Closure
     */
    private $proceedMock;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        // We need to use getMockBuilder to have more control over the mock
        $this->responseMock = $this
            ->getMockBuilder(ResponseHttp::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Configure the response mock to return itself from method calls
        $this->responseMock->method('setHeader')->willReturnSelf();
        $this->responseMock->method('setBody')->willReturnSelf();
        $this->responseMock->method('setHttpResponseCode')->willReturnSelf();
        $this->responseMock->method('setStatusCode')->willReturnSelf();

        $this->subjectMock = $this->createMock(FrontControllerInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->curlMock = $this->createMock(Curl::class);
        $this->paymentHelperMock = $this->createMock(PaymentHelper::class);
        $this->methodMock = $this->createMock(MethodInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $this->proceedMock = function ($request) {
            return 'proceed_result';
        };

        $this->plugin = new ApplePayVerification(
            $this->loggerMock,
            $this->responseMock,
            $this->curlMock,
            $this->paymentHelperMock,
            $this->scopeConfigMock
        );
    }

    /**
     * Test that non-Apple Pay verification requests are passed through
     */
    public function testRegularRequestsPassThrough(): void
    {
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/some/other/path');

        $result = $this->plugin->aroundDispatch(
            $this->subjectMock,
            $this->proceedMock,
            $this->requestMock
        );

        $this->assertEquals('proceed_result', $result);
    }

    /**
     * Test the Apple Pay verification file is served successfully when enabled
     */
    public function testApplePayVerificationFileIsServedWhenEnabled(): void
    {
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        $this
            ->paymentHelperMock
            ->expects($this->once())
            ->method('getMethodInstance')
            ->with('monei_google_apple')
            ->willReturn($this->methodMock);

        $this
            ->methodMock
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('get')
            ->with('https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/');

        $this
            ->curlMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(200);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('getBody')
            ->willReturn('verification_file_content');

        $this
            ->responseMock
            ->expects($this->once())
            ->method('setHeader')
            ->with('Content-Type', 'text/plain')
            ->willReturnSelf();

        $this
            ->responseMock
            ->expects($this->once())
            ->method('setBody')
            ->with('verification_file_content')
            ->willReturnSelf();

        $this
            ->responseMock
            ->expects($this->once())
            ->method('setStatusCode')
            ->with(200)
            ->willReturnSelf();

        $result = $this->plugin->aroundDispatch(
            $this->subjectMock,
            $this->proceedMock,
            $this->requestMock
        );

        // The method should return the response object
        $this->assertSame($this->responseMock, $result);
    }

    /**
     * Test handling when the Apple Pay verification file request fails
     */
    public function testHandlesRequestFailure(): void
    {
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        $this
            ->paymentHelperMock
            ->expects($this->once())
            ->method('getMethodInstance')
            ->with('monei_google_apple')
            ->willReturn($this->methodMock);

        $this
            ->methodMock
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('get')
            ->with('https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/');

        $this
            ->curlMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(404);

        $this
            ->responseMock
            ->expects($this->once())
            ->method('setStatusCode')
            ->with(404)
            ->willReturnSelf();

        $result = $this->plugin->aroundDispatch(
            $this->subjectMock,
            $this->proceedMock,
            $this->requestMock
        );

        // The method should return the response object
        $this->assertSame($this->responseMock, $result);
    }

    /**
     * Test handling when an exception occurs during the request
     */
    public function testHandlesException(): void
    {
        $this
            ->requestMock
            ->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        $this
            ->paymentHelperMock
            ->expects($this->once())
            ->method('getMethodInstance')
            ->with('monei_google_apple')
            ->willReturn($this->methodMock);

        $this
            ->methodMock
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this
            ->curlMock
            ->expects($this->once())
            ->method('get')
            ->with('https://assets.monei.com/apple-pay/apple-developer-merchantid-domain-association/')
            ->willThrowException(new \Exception('Connection error'));

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with('[ApplePay] Error serving verification file: Connection error');

        $this
            ->responseMock
            ->expects($this->once())
            ->method('setStatusCode')
            ->with(500)
            ->willReturnSelf();

        $result = $this->plugin->aroundDispatch(
            $this->subjectMock,
            $this->proceedMock,
            $this->requestMock
        );

        // The method should return the response object
        $this->assertSame($this->responseMock, $result);
    }
}

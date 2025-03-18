<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Model\ApplePayDomainRegister200Response;
use Monei\Model\RegisterApplePayDomainRequest;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Api\VerifyApplePayDomain;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
use Monei\ApplePayDomainApi;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Create mock class for ApplePayDomainRegister200Response to avoid type errors
 */
class MockApplePayDomainRegister200Response extends ApplePayDomainRegister200Response
{
    private $domainName;
    private $status;

    public function __construct($domainName = '', $status = 'verified')
    {
        $this->domainName = $domainName;
        $this->status = $status;
    }

    public function getDomainName()
    {
        return $this->domainName;
    }

    public function getStatus()
    {
        return $this->status;
    }
}

class VerifyApplePayDomainTest extends TestCase
{
    /**
     * @var VerifyApplePayDomain
     */
    private VerifyApplePayDomain $verifyApplePayDomainService;

    /**
     * @var Logger|MockObject
     */
    private Logger $loggerMock;

    /**
     * @var ApiExceptionHandler|MockObject
     */
    private ApiExceptionHandler $exceptionHandlerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $apiClientMock;

    /**
     * @var MoneiClient|MockObject
     */
    private MoneiClient $moneiClientMock;

    /**
     * @var MockObject
     */
    private $applePayDomainApiMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->moneiClientMock = $this
            ->getMockBuilder(MoneiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->applePayDomainApiMock = $this->getMockBuilder(\stdClass::class)->addMethods(['register'])->getMock();

        // Configure Monei client mock
        $this->moneiClientMock->applePayDomain = $this->applePayDomainApiMock;

        // Configure API client to return the Monei client mock
        $this->apiClientMock->method('getMoneiSdk')->willReturn($this->moneiClientMock);
        $this->apiClientMock->method('convertResponseToArray')->willReturn(['status' => 'verified']);

        $this->verifyApplePayDomainService = new VerifyApplePayDomain(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock
        );
    }

    public function testExecuteWithValidDomain(): void
    {
        $domain = 'example.com';
        $storeId = 1;

        // Create proper mock response with correct type
        $responseMock = new MockApplePayDomainRegister200Response($domain, 'verified');

        // Configure ApplePayDomainApi to return the mock response
        $this
            ->applePayDomainApiMock
            ->method('register')
            ->willReturn($responseMock);

        // Verify the SDK was called with the correct store ID
        $this
            ->apiClientMock
            ->expects($this->once())
            ->method('getMoneiSdk')
            ->with($this->equalTo($storeId))
            ->willReturn($this->moneiClientMock);

        // Execute the service
        $result = $this->verifyApplePayDomainService->execute($domain, $storeId);

        // Verify the result
        $this->assertSame($responseMock, $result);
        $this->assertEquals('verified', $result->getStatus());
        $this->assertEquals($domain, $result->getDomainName());
    }

    public function testExecuteWithEmptyDomain(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Domain is required for Apple Pay verification');

        $this->verifyApplePayDomainService->execute('');
    }

    public function testExecuteWithApiException(): void
    {
        $domain = 'invalid-domain.com';

        // Create API exception
        $apiException = new ApiException('Domain verification failed');

        // Configure ApplePayDomainApi to throw the exception
        $this
            ->applePayDomainApiMock
            ->method('register')
            ->willThrowException($apiException);

        // Configure exception handler to rethrow as LocalizedException
        $this
            ->exceptionHandlerMock
            ->method('handle')
            ->withAnyParameters()
            ->willThrowException(new LocalizedException(__('Domain verification failed')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Domain verification failed');

        $this->verifyApplePayDomainService->execute($domain);
    }

    /**
     * Test that default null storeId works correctly
     */
    public function testExecuteWithDefaultStoreId(): void
    {
        $domain = 'example.com';

        // Create proper mock response with correct type
        $responseMock = new MockApplePayDomainRegister200Response($domain, 'verified');

        // Configure ApplePayDomainApi to return the mock response
        $this
            ->applePayDomainApiMock
            ->method('register')
            ->willReturn($responseMock);

        // Verify the SDK was called with null store ID
        $this
            ->apiClientMock
            ->expects($this->once())
            ->method('getMoneiSdk')
            ->with($this->equalTo(null))
            ->willReturn($this->moneiClientMock);

        // Execute the service with default null storeId
        $result = $this->verifyApplePayDomainService->execute($domain);

        $this->assertSame($responseMock, $result);
    }
}

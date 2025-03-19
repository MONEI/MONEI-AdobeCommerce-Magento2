<?php

/**
 * Test for the VerifyApplePayDomain API service
 *
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\Api\ApplePayDomainApi;
use Monei\Model\ApplePayDomainRegister200Response;
use Monei\Model\RegisterApplePayDomainRequest;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Api\VerifyApplePayDomain;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Service\Api\MockApplePayDomainRegister200Response;
use Monei\ApiException;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VerifyApplePayDomain API service
 *
 * php version 8.1
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
class VerifyApplePayDomainTest extends TestCase
{
    /**
     * VerifyApplePayDomain service instance
     *
     * @var VerifyApplePayDomain
     */
    private VerifyApplePayDomain $_verifyApplePayDomainService;

    /**
     * Logger mock
     *
     * @var Logger|MockObject
     */
    private Logger $_loggerMock;

    /**
     * Exception handler mock
     *
     * @var ApiExceptionHandler|MockObject
     */
    private ApiExceptionHandler $_exceptionHandlerMock;

    /**
     * API client mock
     *
     * @var MoneiApiClient|MockObject
     */
    private MoneiApiClient $_apiClientMock;

    /**
     * MoneiClient mock
     *
     * @var MoneiClient|MockObject
     */
    private MoneiClient $_moneiClientMock;

    /**
     * ApplePayDomainApi mock
     *
     * @var ApplePayDomainApi|MockObject
     */
    private ApplePayDomainApi $_applePayDomainApiMock;

    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        // Create mock for ApplePayDomainApi
        $this->_applePayDomainApiMock = $this->createMock(ApplePayDomainApi::class);

        // Create mock for MoneiClient with applePayDomain property
        $this->_moneiClientMock = $this->createMock(MoneiClient::class);
        // Set up the applePayDomain property to provide access to the ApplePayDomainApi
        $this->_moneiClientMock->applePayDomain = $this->_applePayDomainApiMock;

        // Create API client mock that returns our MoneiClient mock
        $this->_apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->_apiClientMock->method('getMoneiSdk')->willReturn($this->_moneiClientMock);
        $this->_apiClientMock->method('convertResponseToArray')->willReturn(['status' => 'verified']);

        $this->_verifyApplePayDomainService = new VerifyApplePayDomain(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock
        );
    }

    /**
     * Test execute with valid domain
     *
     * @return void
     */
    public function testExecuteWithValidDomain(): void
    {
        $domain = 'example.com';
        $storeId = 1;

        // Create a response using our mock class
        $responseMock = new MockApplePayDomainRegister200Response($domain, 'verified');

        // Configure ApplePayDomainApi to return the mock response
        $this
            ->_applePayDomainApiMock
            ->expects($this->once())
            ->method('register')
            ->willReturn($responseMock);

        // Verify the SDK was called with the correct store ID
        $this
            ->_apiClientMock
            ->expects($this->once())
            ->method('getMoneiSdk')
            ->with($this->equalTo($storeId))
            ->willReturn($this->_moneiClientMock);

        // Execute the service
        $result = $this->_verifyApplePayDomainService->execute($domain, $storeId);

        // Verify the result
        $this->assertSame($responseMock, $result);
        $this->assertEquals('verified', $result->getStatus());
        $this->assertEquals($domain, $result->getDomainName());
    }

    /**
     * Test execute with empty domain
     *
     * @return void
     */
    public function testExecuteWithEmptyDomain(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Domain is required for Apple Pay verification');

        $this->_verifyApplePayDomainService->execute('');
    }

    /**
     * Test execute with API exception
     *
     * @return void
     */
    public function testExecuteWithApiException(): void
    {
        $domain = 'invalid-domain.com';

        // Create API exception
        $apiException = new ApiException('Domain verification failed');

        // Configure ApplePayDomainApi to throw the exception
        $this
            ->_applePayDomainApiMock
            ->method('register')
            ->willThrowException($apiException);

        // Configure exception handler to rethrow as LocalizedException
        $this
            ->_exceptionHandlerMock
            ->method('handle')
            ->withAnyParameters()
            ->willThrowException(new LocalizedException(__('Domain verification failed')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Domain verification failed');

        $this->_verifyApplePayDomainService->execute($domain);
    }

    /**
     * Test that default null storeId works correctly
     *
     * @return void
     */
    public function testExecuteWithDefaultStoreId(): void
    {
        $domain = 'example.com';

        // Create a response using our mock class
        $responseMock = new MockApplePayDomainRegister200Response($domain, 'verified');

        // Configure ApplePayDomainApi to return the mock response
        $this
            ->_applePayDomainApiMock
            ->method('register')
            ->willReturn($responseMock);

        // Verify the SDK was called with null store ID
        $this
            ->_apiClientMock
            ->expects($this->once())
            ->method('getMoneiSdk')
            ->with($this->equalTo(null))
            ->willReturn($this->_moneiClientMock);

        // Execute the service with default null storeId
        $result = $this->_verifyApplePayDomainService->execute($domain);

        $this->assertSame($responseMock, $result);
    }
}

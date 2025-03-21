<?php

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Controller\Payment\Callback;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;
use PHPUnit\Framework\TestCase;

/**
 * Test for the CSRF protection methods in the Callback controller
 */
class CallbackTest extends TestCase
{
    /**
     * @var Callback
     */
    private $callbackController;

    /**
     * @var Logger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    /**
     * @var JsonFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $jsonFactoryMock;

    /**
     * @var PaymentProcessorInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentProcessorMock;

    /**
     * @var MoneiApiClient|\PHPUnit\Framework\MockObject\MockObject
     */
    private $apiClientMock;

    /**
     * @var OrderRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var HttpRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestMock;

    /**
     * @var HttpResponse|\PHPUnit\Framework\MockObject\MockObject
     */
    private $responseMock;

    /**
     * @var PaymentDTOFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentDtoFactoryMock;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configMock;

    /**
     * @var Json|\PHPUnit\Framework\MockObject\MockObject
     */
    private $jsonResponseMock;

    /**
     * @var \Monei\MoneiClient|\PHPUnit\Framework\MockObject\MockObject
     */
    private $moneiSdkMock;

    /**
     * @var PaymentDTO|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paymentDtoMock;

    /**
     * @var PaymentProcessingResultInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $processingResultMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->jsonFactoryMock = $this->createMock(JsonFactory::class);
        $this->paymentProcessorMock = $this->createMock(PaymentProcessorInterface::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->requestMock = $this->createMock(HttpRequest::class);
        $this->responseMock = $this->createMock(HttpResponse::class);
        $this->paymentDtoFactoryMock = $this->createMock(PaymentDTOFactory::class);
        $this->configMock = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->jsonResponseMock = $this->createMock(Json::class);
        $this->moneiSdkMock = $this->createMock(\Monei\MoneiClient::class);
        $this->paymentDtoMock = $this->createMock(PaymentDTO::class);
        $this->processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);

        $this->jsonFactoryMock->method('create')->willReturn($this->jsonResponseMock);
        $this->apiClientMock->method('getMoneiSdk')->willReturn($this->moneiSdkMock);

        $this->callbackController = new Callback(
            $this->loggerMock,
            $this->jsonFactoryMock,
            $this->paymentProcessorMock,
            $this->apiClientMock,
            $this->orderRepositoryMock,
            $this->requestMock,
            $this->responseMock,
            $this->paymentDtoFactoryMock,
            $this->configMock
        );
    }

    /**
     * Test that the CSRF validation exception returns the expected type
     */
    public function testCreateCsrfValidationException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(HttpResponse::class);

        // Set expected HTTP code
        $response
            ->expects($this->once())
            ->method('setHttpResponseCode')
            ->with(403);

        // Set reason phrase (empty string in our test)
        $response
            ->expects($this->once())
            ->method('setReasonPhrase')
            ->with('');

        // Use the main test controller with modified response
        $this->responseMock = $response;
        $callback = new Callback(
            $this->loggerMock,
            $this->jsonFactoryMock,
            $this->paymentProcessorMock,
            $this->apiClientMock,
            $this->orderRepositoryMock,
            $this->requestMock,
            $response,
            $this->paymentDtoFactoryMock,
            $this->configMock
        );

        // Get exception
        $exception = $callback->createCsrfValidationException($request);

        // Verify exception type
        $this->assertInstanceOf(InvalidRequestException::class, $exception);
    }

    /**
     * Test CSRF validation with missing signature
     */
    public function testValidateForCsrfWithMissingSignature(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger
            ->expects($this->once())
            ->method('critical')
            ->with('[Callback CSRF] Missing signature header');

        // Create controller for testing
        $callback = new Callback(
            $logger,
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $this->createMock(MoneiApiClient::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMockRequestWithContent('{}', null),
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute validation and check result
        $request = $this->createMockRequestWithContent('{}', null);
        $result = $callback->validateForCsrf($request);
        $this->assertFalse($result);
    }

    /**
     * Test validate for CSRF with valid signature
     */
    public function testValidateForCsrfWithValidSignature(): void
    {
        // Use the same test payload format as MONEI PHP SDK
        $timestamp = '1602604555';
        $rawBody = '{"id":"3690bd3f7294db82fed08c7371bace32","amount":11700,"currency":"EUR","orderId":"000000001","status":"SUCCEEDED","message":"Transaction Approved"}';

        // Generate signature in the same format as the SDK tests
        $hmac = hash_hmac('SHA256', $timestamp . '.' . $rawBody, 'test_api_key');
        $signature = "t={$timestamp},v1={$hmac}";

        // Expected payment data (the Object that would be returned from verifySignature)
        $paymentData = json_decode($rawBody);

        // Create mock for MoneiSdk
        $moneiSdkMock = $this
            ->getMockBuilder(\Monei\MoneiClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Have the mock return a stdClass (simulating decoded payment data)
        $moneiSdkMock
            ->method('verifySignature')
            ->with($this->anything(), $signature)
            ->willReturn($paymentData);

        // Create API client mock returning our SDK mock
        $apiClientMock = $this->createMock(MoneiApiClient::class);
        $apiClientMock->method('getMoneiSdk')->willReturn($moneiSdkMock);

        // Create logger mock that allows multiple calls
        $loggerMock = $this->createMock(Logger::class);

        // Create request mock
        $requestMock = $this->createMockRequestWithContent($rawBody, $signature);

        // Create controller with our mocks
        $controller = new Callback(
            $loggerMock,
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $requestMock,
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute the validateForCsrf method
        $result = $controller->validateForCsrf($requestMock);

        // Assert result is true (valid signature)
        $this->assertTrue($result);
    }

    /**
     * Test validate for CSRF with missing header
     */
    public function testValidateForCsrfWithMissingHeader(): void
    {
        // Use a simple payload
        $rawBody = '{"id":"test123","amount":10000}';

        // Create logger mock that expects critical error to be logged for missing header
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->expects($this->once())
            ->method('critical')
            ->with('[Callback CSRF] Missing signature header');

        // Create controller with a null signature
        $controller = new Callback(
            $loggerMock,
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $this->createMock(MoneiApiClient::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMockRequestWithContent($rawBody, null),
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute the validateForCsrf method with our mocked request
        $requestMock = $this->createMockRequestWithContent($rawBody, null);
        $result = $controller->validateForCsrf($requestMock);

        // Assert result is false (invalid due to missing header)
        $this->assertFalse($result);
    }

    /**
     * Test validate for CSRF with exception during validation
     */
    public function testValidateForCsrfWithException(): void
    {
        // Use a simple payload
        $rawBody = '{"id":"test123","amount":10000}';
        $signature = 'valid_signature';

        // Create request mock
        $requestMock = $this->createMockRequestWithContent($rawBody, $signature);

        // Create moneiSdkMock to throw exception when verifying signature
        $moneiSdkMock = $this
            ->getMockBuilder(\Monei\MoneiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $moneiSdkMock
            ->method('verifySignature')
            ->with($this->anything(), $signature)
            ->willThrowException(new \Exception('Test exception during signature verification'));

        // Create API client mock
        $apiClientMock = $this->createMock(MoneiApiClient::class);
        $apiClientMock->method('getMoneiSdk')->willReturn($moneiSdkMock);

        // Create logger mock that expects critical error to be logged for exception
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->expects($this->once())
            ->method('critical')
            ->with($this->stringContains('[Callback CSRF] Test exception during signature verification'));

        // Create controller with our mocks
        $controller = new Callback(
            $loggerMock,
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $requestMock,
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute the validateForCsrf method
        $result = $controller->validateForCsrf($requestMock);

        // Assert result is false (exception during validation)
        $this->assertFalse($result);
    }

    /**
     * Test execute with successful payment
     */
    public function testExecuteWithSuccessfulPayment(): void
    {
        // Use the same test payload format as MONEI PHP SDK
        $timestamp = '1602604555';
        $rawBody = '{"id":"3690bd3f7294db82fed08c7371bace32","amount":11700,"currency":"EUR","orderId":"000000001","status":"SUCCEEDED","message":"Transaction Approved"}';

        // Generate signature in the same format as the SDK tests
        $hmac = hash_hmac('SHA256', $timestamp . '.' . $rawBody, 'test_api_key');
        $signature = "t={$timestamp},v1={$hmac}";

        // Create request mock
        $requestMock = $this->createMockRequestWithContent($rawBody, $signature);

        // Expected payment data (the Object that would be returned from verifySignature)
        $paymentData = json_decode($rawBody);

        // Create a fully mocked instance for this test
        $jsonResponseMock = $this->createMock(Json::class);
        $jsonFactoryMock = $this->createMock(JsonFactory::class);
        $jsonFactoryMock->method('create')->willReturn($jsonResponseMock);

        // Setup result data for successful response - allow any parameters for setData
        $jsonResponseMock
            ->method('setData')
            ->willReturnSelf();

        // Also allow setHttpResponseCode to be called (for error cases)
        $jsonResponseMock
            ->method('setHttpResponseCode')
            ->willReturnSelf();

        // Create moneiSdkMock to return verified data
        $moneiSdkMock = $this
            ->getMockBuilder(\Monei\MoneiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $moneiSdkMock
            ->method('verifySignature')
            ->with($this->anything(), $signature)
            ->willReturn($paymentData);

        // Create API client mock
        $apiClientMock = $this->createMock(MoneiApiClient::class);
        $apiClientMock->method('getMoneiSdk')->willReturn($moneiSdkMock);

        // Create DTO mock and factory
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getId')->willReturn($paymentData->id);
        $paymentDtoMock->method('getOrderId')->willReturn($paymentData->orderId);
        $paymentDtoMock->method('getStatus')->willReturn($paymentData->status);
        $paymentDtoMock->method('getRawData')->willReturn((array) $paymentData);

        $paymentDtoFactoryMock = $this->createMock(PaymentDTOFactory::class);
        $paymentDtoFactoryMock
            ->method('createFromArray')
            ->with($this->isType('array'))
            ->willReturn($paymentDtoMock);

        // Create successful processing result
        $processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);
        $processingResultMock->method('isSuccess')->willReturn(true);
        $processingResultMock->method('getMessage')->willReturn('Payment processed successfully');

        // Create payment processor mock
        $paymentProcessorMock = $this->createMock(PaymentProcessorInterface::class);
        $paymentProcessorMock
            ->method('process')
            ->with(
                $this->equalTo($paymentData->orderId),
                $this->equalTo($paymentData->id),
                $this->isType('array')
            )
            ->willReturn($processingResultMock);

        // Create logger mock that allows multiple calls
        $loggerMock = $this->createMock(Logger::class);

        // Create response mock
        $responseMock = $this->createMock(HttpResponse::class);
        $responseMock->method('setHeader')->willReturnSelf();

        // Create controller with all our mocks
        $controller = new Callback(
            $loggerMock,
            $jsonFactoryMock,
            $paymentProcessorMock,
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $requestMock,
            $responseMock,
            $paymentDtoFactoryMock,
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute controller
        $result = $controller->execute();

        // Assert returned the JSON response
        $this->assertSame($jsonResponseMock, $result);
    }

    /**
     * Test execute with payment DTO exception
     */
    public function testExecuteWithPaymentDtoException(): void
    {
        // Further test implementation...
    }

    /**
     * Helper method to create a mocked request with content and signature
     *
     * @param string $content Request content
     * @param string|null $signature MONEI-Signature header value
     * @return RequestInterface The mocked request
     */
    private function createMockRequestWithContent(string $content, ?string $signature): RequestInterface
    {
        $requestMock = $this->createMock(HttpRequest::class);
        $requestMock->method('getContent')->willReturn($content);
        $requestMock
            ->method('getHeader')
            ->with('MONEI-Signature')
            ->willReturn($signature);
        return $requestMock;
    }
}

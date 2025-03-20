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
use Monei\MoneiPayment\Test\Unit\Util\PhpStreamWrapper;
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
        // Save original SERVER var if it exists
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Ensure signature is not set
        unset($_SERVER['HTTP_MONEI_SIGNATURE']);

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
            $this->createMock(HttpRequest::class),
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute validation and check result
        $result = $callback->validateForCsrf($this->createMock(RequestInterface::class));
        $this->assertFalse($result);

        // Restore original SERVER var if it existed
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        }
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

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Set signature header
        $_SERVER['HTTP_MONEI_SIGNATURE'] = $signature;

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
        $loggerMock
            ->method('debug')
            ->willReturnCallback(function () {
                // Debug is a void method, so don't return anything
            });
        $loggerMock
            ->method('critical')
            ->willReturnCallback(function () {
                // Critical is a void method, so don't return anything
            });

        // Create controller with our mocks
        $controller = new Callback(
            $loggerMock,
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(HttpRequest::class),
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute the validateForCsrf method
        $result = $controller->validateForCsrf($this->createMock(RequestInterface::class));

        // Assert result is true (valid signature)
        $this->assertTrue($result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        } else {
            unset($_SERVER['HTTP_MONEI_SIGNATURE']);
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
    }

    /**
     * Test validate for CSRF with missing header
     */
    public function testValidateForCsrfWithMissingHeader(): void
    {
        // Use a simple payload
        $rawBody = '{"id":"test123","amount":10000}';

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Remove the signature header
        unset($_SERVER['HTTP_MONEI_SIGNATURE']);

        // Create logger mock that expects critical error to be logged for missing header
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->expects($this->once())
            ->method('critical')
            ->with('[Callback CSRF] Missing signature header')
            ->willReturnCallback(function () {
                // Critical is a void method, so don't return anything
            });

        // Create controller with our mocks
        $controller = new Callback(
            $loggerMock,
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $this->createMock(MoneiApiClient::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(HttpRequest::class),
            $this->createMock(HttpResponse::class),
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute the validateForCsrf method
        $result = $controller->validateForCsrf($this->createMock(RequestInterface::class));

        // Assert result is false (invalid due to missing header)
        $this->assertFalse($result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
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

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Set signature header
        $_SERVER['HTTP_MONEI_SIGNATURE'] = $signature;

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
        $loggerMock
            ->method('debug')
            ->willReturnCallback(function () {
                // Debug is a void method, so don't return anything
            });
        $loggerMock
            ->method('error')
            ->willReturnCallback(function () {
                // Error is a void method, so don't return anything
            });
        $loggerMock
            ->method('critical')
            ->willReturnCallback(function () {
                // Critical is a void method, so don't return anything
            });

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
            $this->createMock(HttpRequest::class),
            $responseMock,
            $paymentDtoFactoryMock,
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute controller
        $result = $controller->execute();

        // Assert returned the JSON response
        $this->assertSame($jsonResponseMock, $result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        } else {
            unset($_SERVER['HTTP_MONEI_SIGNATURE']);
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
    }

    /**
     * Test execute with missing required fields
     */
    public function testExecuteWithMissingRequiredFields(): void
    {
        // Use the same test payload format but missing orderId
        $timestamp = '1602604555';
        $rawBody = '{"id":"3690bd3f7294db82fed08c7371bace32","amount":11700,"currency":"EUR","status":"SUCCEEDED","message":"Transaction Approved"}';

        // Generate signature in the same format as the SDK tests
        $hmac = hash_hmac('SHA256', $timestamp . '.' . $rawBody, 'test_api_key');
        $signature = "t={$timestamp},v1={$hmac}";

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Set signature header
        $_SERVER['HTTP_MONEI_SIGNATURE'] = $signature;

        // Expected payment data (the Object that would be returned from verifySignature)
        $paymentData = json_decode($rawBody);

        // Create a fully mocked instance for this test
        $jsonResponseMock = $this->createMock(Json::class);
        $jsonFactoryMock = $this->createMock(JsonFactory::class);
        $jsonFactoryMock->method('create')->willReturn($jsonResponseMock);

        // Setup setData to return self for any parameters
        $jsonResponseMock
            ->method('setData')
            ->willReturnSelf();

        // Setup setHttpResponseCode to return self and expect 500 status code
        $jsonResponseMock
            ->method('setHttpResponseCode')
            ->with(500)
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

        // Create logger mock that expects error to be called when fields are missing
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->method('debug')
            ->willReturnCallback(function () {
                // Debug is a void method, so don't return anything
            });
        $loggerMock
            ->expects($this->atLeastOnce())
            ->method('error')
            ->willReturnCallback(function () {
                // Error is a void method, so don't return anything
            });

        // Create response mock
        $responseMock = $this->createMock(HttpResponse::class);
        $responseMock->method('setHeader')->willReturnSelf();

        // Create controller with all our mocks
        $controller = new Callback(
            $loggerMock,
            $jsonFactoryMock,
            $this->createMock(PaymentProcessorInterface::class),
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(HttpRequest::class),
            $responseMock,
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute controller
        $result = $controller->execute();

        // Assert returned the JSON response
        $this->assertSame($jsonResponseMock, $result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        } else {
            unset($_SERVER['HTTP_MONEI_SIGNATURE']);
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
    }

    /**
     * Test execute with processing failure
     */
    public function testExecuteWithProcessingFailure(): void
    {
        // Use the same test payload format as MONEI PHP SDK
        $timestamp = '1602604555';
        $rawBody = '{"id":"3690bd3f7294db82fed08c7371bace32","amount":11700,"currency":"EUR","orderId":"000000001","status":"SUCCEEDED","message":"Transaction Approved"}';

        // Generate signature in the same format as the SDK tests
        $hmac = hash_hmac('SHA256', $timestamp . '.' . $rawBody, 'test_api_key');
        $signature = "t={$timestamp},v1={$hmac}";

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Set signature header
        $_SERVER['HTTP_MONEI_SIGNATURE'] = $signature;

        // Expected payment data (the Object that would be returned from verifySignature)
        $paymentData = json_decode($rawBody);

        // Create a fully mocked instance for this test
        $jsonResponseMock = $this->createMock(Json::class);
        $jsonFactoryMock = $this->createMock(JsonFactory::class);
        $jsonFactoryMock->method('create')->willReturn($jsonResponseMock);

        // Setup setData to return self for any parameters
        $jsonResponseMock
            ->method('setData')
            ->willReturnSelf();

        // Setup setHttpResponseCode to return self and expect 500 status code
        $jsonResponseMock
            ->method('setHttpResponseCode')
            ->with(500)
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

        // Create failed processing result
        $processingResultMock = $this->createMock(PaymentProcessingResultInterface::class);
        $processingResultMock->method('isSuccess')->willReturn(false);
        $processingResultMock->method('getMessage')->willReturn('Payment processing failed');

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
        $loggerMock
            ->method('debug')
            ->willReturnCallback(function () {
                // Debug is a void method, so don't return anything
            });
        $loggerMock
            ->expects($this->atLeastOnce())
            ->method('error')
            ->willReturnCallback(function () {
                // Error is a void method, so don't return anything
            });

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
            $this->createMock(HttpRequest::class),
            $responseMock,
            $paymentDtoFactoryMock,
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute controller
        $result = $controller->execute();

        // Assert returned the JSON response
        $this->assertSame($jsonResponseMock, $result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        } else {
            unset($_SERVER['HTTP_MONEI_SIGNATURE']);
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
    }

    /**
     * Test execute with invalid signature
     */
    public function testExecuteWithInvalidSignature(): void
    {
        // Use an empty payload since we'll force signature verification to fail
        $rawBody = '{}';
        $signature = 'invalid_signature';

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Set signature header
        $_SERVER['HTTP_MONEI_SIGNATURE'] = $signature;

        // Create a fully mocked instance for this test
        $jsonResponseMock = $this->createMock(Json::class);
        $jsonFactoryMock = $this->createMock(JsonFactory::class);
        $jsonFactoryMock->method('create')->willReturn($jsonResponseMock);

        // Setup setData to return self for any parameters
        $jsonResponseMock
            ->method('setData')
            ->willReturnSelf();

        // Setup setHttpResponseCode to return self and expect 401 status code
        $jsonResponseMock
            ->method('setHttpResponseCode')
            ->with(401)
            ->willReturnSelf();

        // Create moneiSdkMock to return null for verification failure
        $moneiSdkMock = $this
            ->getMockBuilder(\Monei\MoneiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $moneiSdkMock
            ->method('verifySignature')
            ->with($this->anything(), $signature)
            ->willReturn(null);

        // Create API client mock
        $apiClientMock = $this->createMock(MoneiApiClient::class);
        $apiClientMock->method('getMoneiSdk')->willReturn($moneiSdkMock);

        // Create logger mock that expects error for invalid signature
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->method('debug')
            ->willReturnCallback(function () {
                // Debug is a void method, so don't return anything
            });
        $loggerMock
            ->expects($this->atLeastOnce())
            ->method('error')
            ->willReturnCallback(function () {
                // Error is a void method, so don't return anything
            });

        // Create response mock
        $responseMock = $this->createMock(HttpResponse::class);
        $responseMock->method('setHeader')->willReturnSelf();

        // Create controller with all our mocks
        $controller = new Callback(
            $loggerMock,
            $jsonFactoryMock,
            $this->createMock(PaymentProcessorInterface::class),
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(HttpRequest::class),
            $responseMock,
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute controller
        $result = $controller->execute();

        // Assert returned the JSON response
        $this->assertSame($jsonResponseMock, $result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        } else {
            unset($_SERVER['HTTP_MONEI_SIGNATURE']);
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
    }

    /**
     * Test execute with payment DTO exception
     */
    public function testExecuteWithPaymentDtoException(): void
    {
        // Use the same test payload format as MONEI PHP SDK
        $timestamp = '1602604555';
        $rawBody = '{"id":"3690bd3f7294db82fed08c7371bace32","amount":11700,"currency":"EUR","orderId":"000000001","status":"SUCCEEDED","message":"Transaction Approved"}';

        // Generate signature in the same format as the SDK tests
        $hmac = hash_hmac('SHA256', $timestamp . '.' . $rawBody, 'test_api_key');
        $signature = "t={$timestamp},v1={$hmac}";

        // Set up the input stream mock first
        $this->setUpInputStreamMock($rawBody);

        // Save original server variable
        $originalServer = $_SERVER['HTTP_MONEI_SIGNATURE'] ?? null;

        // Set signature header
        $_SERVER['HTTP_MONEI_SIGNATURE'] = $signature;

        // Expected payment data (the Object that would be returned from verifySignature)
        $paymentData = json_decode($rawBody);

        // Create a fully mocked instance for this test
        $jsonResponseMock = $this->createMock(Json::class);
        $jsonFactoryMock = $this->createMock(JsonFactory::class);
        $jsonFactoryMock->method('create')->willReturn($jsonResponseMock);

        // Setup setData to return self for any parameters
        $jsonResponseMock
            ->method('setData')
            ->willReturnSelf();

        // Setup setHttpResponseCode to return self and expect 500 status code
        $jsonResponseMock
            ->method('setHttpResponseCode')
            ->with(500)
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

        // Create DTO factory mock
        $paymentDtoFactoryMock = $this->createMock(PaymentDTOFactory::class);
        $paymentDtoFactoryMock
            ->method('createFromArray')
            ->willThrowException(new \Exception('Invalid payment data'));

        // Create logger mock that expects error to be called for the exception
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->method('debug')
            ->willReturnCallback(function () {
                // Debug is a void method, so don't return anything
            });
        $loggerMock
            ->expects($this->atLeastOnce())
            ->method('error')
            ->willReturnCallback(function () {
                // Error is a void method, so don't return anything
            });

        // Create response mock
        $responseMock = $this->createMock(HttpResponse::class);
        $responseMock->method('setHeader')->willReturnSelf();

        // Create controller with all our mocks
        $controller = new Callback(
            $loggerMock,
            $jsonFactoryMock,
            $this->createMock(PaymentProcessorInterface::class),
            $apiClientMock,
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(HttpRequest::class),
            $responseMock,
            $paymentDtoFactoryMock,
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
        );

        // Execute controller
        $result = $controller->execute();

        // Assert returned the JSON response
        $this->assertSame($jsonResponseMock, $result);

        // Restore original server variable
        if ($originalServer !== null) {
            $_SERVER['HTTP_MONEI_SIGNATURE'] = $originalServer;
        } else {
            unset($_SERVER['HTTP_MONEI_SIGNATURE']);
        }

        // Teardown the mock stream
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }
    }

    /**
     * Helper method to mock php://input stream
     *
     * @param string $content Content to return when reading from php://input
     * @return void
     */
    private function setUpInputStreamMock(string $content): void
    {
        // Unregister existing wrapper if it exists
        if (in_array('php', stream_get_wrappers())) {
            stream_wrapper_unregister('php');
        }

        // Set the content for our mock
        PhpStreamWrapper::setContent($content);

        // Register our mocked stream wrapper
        stream_wrapper_register('php', PhpStreamWrapper::class);
    }
}

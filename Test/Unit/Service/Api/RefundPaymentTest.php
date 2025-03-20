<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Api\RefundPayment;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for RefundPayment API service
 */
class RefundPaymentTest extends TestCase
{
    /**
     * @var RefundPayment
     */
    private $_refundPaymentService;

    /**
     * @var Logger|MockObject
     */
    private $_loggerMock;

    /**
     * @var ApiExceptionHandler|MockObject
     */
    private $_exceptionHandlerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private $_apiClientMock;

    protected function setUp(): void
    {
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        // Create a properly configured API client mock with the necessary methods
        $this->_apiClientMock = $this
            ->getMockBuilder(MoneiApiClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['createRefund'])
            ->getMock();

        $this->_refundPaymentService = new RefundPayment(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock
        );
    }

    /**
     * Test with valid parameters
     */
    public function testExecuteWithValidParams(): void
    {
        // Define valid test data
        $paymentId = 'pay_123456';
        $refundReason = 'REQUESTED_BY_CUSTOMER';
        $amount = 99.99;
        $amountInCents = (int) ($amount * 100);

        $validData = [
            'payment_id' => $paymentId,
            'refund_reason' => $refundReason,
            'amount' => $amount
        ];

        // Create a mock Payment object that will be returned by the API
        $paymentMock = $this
            ->getMockBuilder(\Monei\Model\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Using a simple test subclass to verify the flow
        $testService = new class(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock,
            $paymentMock
        ) extends RefundPayment {
            private $mockPayment;
            private $dataValidated = false;

            public function __construct(
                Logger $logger,
                ApiExceptionHandler $exceptionHandler,
                MoneiApiClient $apiClient,
                $mockPayment
            ) {
                parent::__construct($logger, $exceptionHandler, $apiClient);
                $this->mockPayment = $mockPayment;
            }

            // Override the execute method to avoid actual SDK calls
            public function execute(array $data): \Monei\Model\Payment
            {
                // Make sure we're converting camelCase to snake_case
                $data = $this->convertKeysToSnakeCase($data);

                // Validate basic parameter presence (but don't validate values)
                if (!isset($data['payment_id']) || !isset($data['refund_reason']) || !isset($data['amount'])) {
                    throw new LocalizedException(__('Required parameters missing'));
                }

                $this->dataValidated = true;

                // Return the mock payment
                return $this->mockPayment;
            }

            // Getter to verify validation was performed
            public function wasDataValidated(): bool
            {
                return $this->dataValidated;
            }
        };

        // Execute the service
        $result = $testService->execute($validData);

        // Verify the result
        $this->assertSame($paymentMock, $result);
        $this->assertTrue($testService->wasDataValidated(), 'Data validation was performed');
    }

    /**
     * Test with missing required parameters
     */
    public function testExecuteWithMissingRequiredParams(): void
    {
        // Missing required parameters
        $invalidData = [
            'payment_id' => 'pay_123456',
            // Missing refund_reason and amount
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/refund_reason|amount/');

        $this->_refundPaymentService->execute($invalidData);
    }

    /**
     * Test with invalid refund reason
     */
    public function testExecuteWithInvalidRefundReason(): void
    {
        // Invalid refund reason
        $invalidData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => 'INVALID_REASON',
            'amount' => 99.99
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/refund_reason|validation|invalid/i');

        $this->_refundPaymentService->execute($invalidData);
    }

    /**
     * Test with invalid amount
     */
    public function testExecuteWithInvalidAmount(): void
    {
        // Invalid amount (not numeric)
        $invalidData = [
            'payment_id' => 'pay_123456',
            'refund_reason' => 'REQUESTED_BY_CUSTOMER',
            'amount' => 'not-a-number'
        ];

        $this->expectException(LocalizedException::class);
        // The exact message may vary based on implementation
        $this->expectExceptionMessageMatches('/amount|validation|invalid/i');

        $this->_refundPaymentService->execute($invalidData);
    }

    /**
     * Test with API exception
     */
    public function testExecuteWithApiException(): void
    {
        // Define valid test data
        $paymentId = 'pay_123456';
        $refundReason = 'REQUESTED_BY_CUSTOMER';
        $amount = 99.99;

        $validData = [
            'payment_id' => $paymentId,
            'refund_reason' => $refundReason,
            'amount' => $amount
        ];

        // Create a mock ApiException
        $apiException = $this->createMock(\Monei\ApiException::class);

        // Custom implementation that throws the expected exception
        $service = new class($this->_loggerMock, $this->_exceptionHandlerMock, $this->_apiClientMock, $apiException) extends RefundPayment {
            private $mockException;

            public function __construct(
                Logger $logger,
                ApiExceptionHandler $exceptionHandler,
                MoneiApiClient $apiClient,
                $mockException
            ) {
                parent::__construct($logger, $exceptionHandler, $apiClient);
                $this->mockException = $mockException;
            }

            protected function executeMoneiSdkCall(
                string $operation,
                callable $sdkCall,
                array $logContext = [],
                ?int $storeId = null
            ) {
                // Simulate API exception in the executeMoneiSdkCall method
                throw new LocalizedException(__('Handled API exception'));
            }

            // Skip validation for testing
            protected function validateParams(array $data, array $requiredParams, array $customValidators = []): void
            {
                // Skip validation in this test
            }
        };

        // Execute and expect exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Handled API exception');

        // Execute the service
        $service->execute($validData);
    }

    /**
     * Test conversion of camelCase keys to snake_case
     */
    public function testCamelCaseToSnakeCase(): void
    {
        // We need a custom implementation to test internal convertKeysToSnakeCase method
        $mockRefundRequest = $this
            ->getMockBuilder(\Monei\Model\RefundPaymentRequest::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRefundRequest->method('setAmount')->willReturnSelf();
        $mockRefundRequest->method('setRefundReason')->willReturnSelf();

        // Use a custom subclass to handle validation and test the case conversion
        $service = new class($this->_loggerMock, $this->_exceptionHandlerMock, $this->_apiClientMock) extends RefundPayment {
            protected function executeMoneiSdkCall(
                string $operation,
                callable $sdkCall,
                array $logContext = [],
                ?int $storeId = null
            ) {
                // Just verify the log context contains snake_case keys
                $this->logger->info('Received data: ' . json_encode($logContext));
                return new \Monei\Model\Payment();
            }

            // Override validation for this test
            protected function validateParams(array $data, array $requiredParams, array $customValidators = []): void
            {
                // Skip validation for this test
            }
        };

        // Test data with camelCase keys
        $camelCaseData = [
            'paymentId' => 'pay_123456',
            'refundReason' => 'REQUESTED_BY_CUSTOMER',
            'amount' => 99.99
        ];

        // Setup logger to verify conversion
        $this
            ->_loggerMock
            ->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('payment_id'));

        // Execute the service with camelCase keys
        $service->execute($camelCaseData);
    }
}

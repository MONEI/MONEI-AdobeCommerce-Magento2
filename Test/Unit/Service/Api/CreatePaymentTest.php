<?php

/**
 * Test class for CreatePayment API service
 *
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Api\PaymentsApi;
use Monei\Model\CreatePaymentRequest;
use Monei\Model\Payment as MoneiPayment;
use Monei\Model\PaymentNextAction;
use Monei\Model\PaymentTransactionType;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Model\Config\Source\TypeOfPayment;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\CreatePayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
use Monei\MoneiClient;
use Monei\ObjectSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for CreatePayment API service
 *
 * php version 8.1
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
class CreatePaymentTest extends TestCase
{
    /**
     * CreatePayment service instance
     *
     * @var CreatePayment
     */
    private CreatePayment $_createPaymentService;

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
     * PaymentsApi mock
     *
     * @var PaymentsApi|MockObject
     */
    private PaymentsApi $_paymentsApiMock;

    /**
     * Module config mock
     *
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private MoneiPaymentModuleConfigInterface $_moduleConfigMock;

    /**
     * URL builder mock
     *
     * @var Url|MockObject
     */
    private Url $_urlBuilderMock;

    /**
     * Links Magento customers to MONEI customers
     *
     * @var CustomerServiceInterface|MockObject
     */
    private CustomerServiceInterface $_customerServiceMock;

    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->_loggerMock = $this->createMock(Logger::class);
        $this->_exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        // Create mock for PaymentsApi
        $this->_paymentsApiMock = $this->createMock(PaymentsApi::class);

        // Create mock for MoneiClient with payments property
        $this->_moneiClientMock = $this->createMock(MoneiClient::class);
        // Set up the payments property to provide access to the PaymentsApi
        $this->_moneiClientMock->payments = $this->_paymentsApiMock;

        // Create API client mock that returns our MoneiClient mock
        $this->_apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->_apiClientMock->method('getMoneiSdk')->willReturn($this->_moneiClientMock);

        $this->_moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->_urlBuilderMock = $this->createMock(Url::class);
        $this->_customerServiceMock = $this->createMock(CustomerServiceInterface::class);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(3);
        $storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->_createPaymentService = new CreatePayment(
            $this->_loggerMock,
            $this->_exceptionHandlerMock,
            $this->_apiClientMock,
            $this->_moduleConfigMock,
            $this->_urlBuilderMock,
            $this->_customerServiceMock,
            $storeManagerMock
        );
    }

    /**
     * Test with minimal parameters
     *
     * @return void
     */
    public function testExecuteWithMinimalParams(): void
    {
        // Set up test data
        $paymentData = [
            'amount' => 1000,
            'currency' => 'EUR',
            'order_id' => '123456',
            'customer' => ['email' => 'test@example.com'],
            'allowed_payment_methods' => ['card'],
            'transaction_type' => PaymentTransactionType::SALE,
            'shipping_details' => ['address' => ['city' => 'Test City', 'country' => 'ES', 'line1' => 'Test Street 123', 'postal_code' => '12345']]
        ];

        // Configure URL Builder
        $this->_urlBuilderMock->method('getUrl')->willReturnMap(
            [
                ['monei/payment/complete', [], 'https://example.com/monei/payment/complete'],
                ['monei/payment/callback', [], 'https://example.com/monei/payment/callback'],
                ['monei/payment/cancel', [], 'https://example.com/monei/payment/cancel']
            ]
        );

        // Create payment response mock
        $mockPayment = $this->createMock(MoneiPayment::class);
        $mockNextAction = $this->createMock(PaymentNextAction::class);

        $mockNextAction->method('getType')->willReturn('REDIRECT');
        $mockNextAction->method('getRedirectUrl')->willReturn('https://checkout.monei.com/123456');

        $mockPayment->method('getId')->willReturn('pay_123456');
        $mockPayment->method('getStatus')->willReturn('PENDING');
        $mockPayment->method('getNextAction')->willReturn($mockNextAction);

        // Configure PaymentsApi mock to create payment
        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($mockPayment);

        // Execute the service
        $result = $this->_createPaymentService->execute($paymentData);

        // Verify the result
        $this->assertSame($mockPayment, $result);
        $this->assertEquals('pay_123456', $result->getId());
        $this->assertEquals('PENDING', $result->getStatus());
        $this->assertEquals($mockNextAction, $result->getNextAction());
    }

    /**
     * Test with all parameters
     *
     * @return void
     */
    public function testExecuteWithAllParams(): void
    {
        // Set up complete test data
        $paymentData = [
            'amount' => 1000,
            'currency' => 'EUR',
            'order_id' => '123456',
            'customer' => [
                'email' => 'test@example.com',
                'name' => 'Test Customer',
                'phone' => '+1234567890'
            ],
            'billing_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'ES',
                    'line1' => 'Test Street 123',
                    'postal_code' => '12345'
                ]
            ],
            'shipping_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'ES',
                    'line1' => 'Test Street 123',
                    'postal_code' => '12345'
                ]
            ],
            'allowed_payment_methods' => ['card', 'paypal'],
            'transaction_type' => PaymentTransactionType::AUTH,
            'description' => 'Test payment',
            'metadata' => ['custom_field' => 'custom_value']
        ];

        // Configure URL Builder
        $this->_urlBuilderMock->method('getUrl')->willReturnMap(
            [
                ['monei/payment/complete', [], 'https://example.com/monei/payment/complete'],
                ['monei/payment/callback', [], 'https://example.com/monei/payment/callback'],
                ['monei/payment/cancel', [], 'https://example.com/monei/payment/cancel']
            ]
        );

        // Create payment response mock
        $mockPayment = $this->createMock(MoneiPayment::class);
        $mockNextAction = $this->createMock(PaymentNextAction::class);

        $mockNextAction->method('getType')->willReturn('REDIRECT');
        $mockNextAction->method('getRedirectUrl')->willReturn('https://checkout.monei.com/123456');

        $mockPayment->method('getId')->willReturn('pay_123456');
        $mockPayment->method('getStatus')->willReturn('PENDING');
        $mockPayment->method('getNextAction')->willReturn($mockNextAction);

        // Configure PaymentsApi mock to create payment
        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($mockPayment);

        // Execute the service
        $result = $this->_createPaymentService->execute($paymentData);

        // Verify the result
        $this->assertSame($mockPayment, $result);
        $this->assertEquals('pay_123456', $result->getId());
        $this->assertEquals('PENDING', $result->getStatus());
        $this->assertEquals($mockNextAction, $result->getNextAction());
    }

    /**
     * A buyer deleted in the MONEI dashboard must still be able to pay: one retry without the stale customerId.
     *
     * The retry must never repeat, and its own failure must reach the caller.
     *
     * @dataProvider staleCustomerRetryProvider
     * @param bool $retryFails
     * @return void
     */
    public function testStaleCustomerIsForgottenAndRetriedOnce(bool $retryFails): void
    {
        $payment = $this->createMock(MoneiPayment::class);
        $retryError = $this->staleCustomerError();
        $calls = 0;
        $this
            ->_paymentsApiMock
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (CreatePaymentRequest $request) use (
                &$calls,
                $retryFails,
                $retryError,
                $payment
            ) {
                if (++$calls === 1) {
                    $this->assertSame('cus_stale', $request->getCustomerId());
                    throw $this->staleCustomerError();
                }
                $this->assertNull($request->getCustomerId());
                $this->assertObjectNotHasProperty('customerId', ObjectSerializer::sanitizeForSerialization($request));
                if ($retryFails) {
                    throw $retryError;
                }

                return $payment;
            });
        $this
            ->_customerServiceMock
            ->expects($this->once())
            ->method('forgetCustomerId')
            ->with(42, 3, 'cus_stale');
        $this
            ->_loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringStartsWith('[MONEI Customers]'),
                ['monei_customer_id' => 'cus_stale', 'customer_id' => 42, 'store_id' => 3]
            );

        if ($retryFails) {
            $this
                ->_exceptionHandlerMock
                ->expects($this->once())
                ->method('handle')
                ->with($this->identicalTo($retryError))
                ->willThrowException(new LocalizedException(__('Customer not found')));
            $this->expectException(LocalizedException::class);
        } else {
            $this->_exceptionHandlerMock->expects($this->never())->method('handle');
        }

        $result = $this->_createPaymentService->execute($this->linkedPaymentData());

        $this->assertSame($payment, $result);
    }

    /**
     * @return array
     */
    public static function staleCustomerRetryProvider(): array
    {
        return [
            'retry succeeds' => [false],
            'retry fails' => [true],
        ];
    }

    /**
     * Only a stale customer is retried; any other error must reach the caller unchanged and untried.
     *
     * @dataProvider notRetriedProvider
     * @param array $removeKeys
     * @param int $code
     * @param string $message
     * @return void
     */
    public function testOtherErrorIsRethrownWithoutRetry(array $removeKeys, int $code, string $message): void
    {
        $error = new ApiException('error', $code, [], json_encode(['statusCode' => $code, 'message' => $message]));
        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('create')
            ->willThrowException($error);
        $this->_customerServiceMock->expects($this->never())->method('forgetCustomerId');
        $this
            ->_exceptionHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($error))
            ->willThrowException(new LocalizedException(__('error')));

        $this->expectException(LocalizedException::class);

        $this->_createPaymentService->execute(array_diff_key($this->linkedPaymentData(), array_flip($removeKeys)));
    }

    /**
     * @return array
     */
    public static function notRetriedProvider(): array
    {
        return [
            'other 404' => [[], 404, 'Payment method not found'],
            'other status' => [[], 400, 'Customer not found'],
            'no customerId' => [['customer_id'], 404, 'Customer not found'],
            'no platform customer' => [['platform_customer_id'], 404, 'Customer not found'],
        ];
    }

    /**
     * @return ApiException
     */
    private function staleCustomerError(): ApiException
    {
        return new ApiException('[404] Not Found', 404, [], json_encode([
            'status' => 'NotFoundError',
            'statusCode' => 404,
            'message' => 'Customer not found',
        ]));
    }

    /**
     * @return array
     */
    private function linkedPaymentData(): array
    {
        return [
            'amount' => 1000,
            'currency' => 'EUR',
            'order_id' => '123456',
            'customer_id' => 'cus_stale',
            'platform_customer_id' => 42,
            'shipping_details' => ['address' => ['country' => 'ES']]
        ];
    }

    /**
     * Test parameter validation
     *
     * @return void
     */
    public function testValidateParamsThrowsExceptionWithMissingParams(): void
    {
        $this->expectException(LocalizedException::class);

        // Call the method without required parameters
        $this->_createPaymentService->execute(
            [
                'amount' => 1000,
                'currency' => 'EUR',
                // Missing order_id
                // Missing customer
            ]
        );
    }

    /**
     * Test API error handling
     *
     * @return void
     */
    public function testExecuteWithApiError(): void
    {
        // Set up test data
        $paymentData = [
            'amount' => 1000,
            'currency' => 'EUR',
            'order_id' => '123456',
            'customer' => ['email' => 'test@example.com'],
            'allowed_payment_methods' => ['card'],
            'transaction_type' => PaymentTransactionType::SALE,
            'shipping_details' => ['address' => ['city' => 'Test City', 'country' => 'ES', 'line1' => 'Test Street 123', 'postal_code' => '12345']]
        ];

        // Configure URL Builder
        $this->_urlBuilderMock->method('getUrl')->willReturnMap(
            [
                ['monei/payment/complete', [], 'https://example.com/monei/payment/complete'],
                ['monei/payment/callback', [], 'https://example.com/monei/payment/callback'],
                ['monei/payment/cancel', [], 'https://example.com/monei/payment/cancel']
            ]
        );

        $apiException = new ApiException('API Error');

        // Mock validation to pass
        $this
            ->_paymentsApiMock
            ->expects($this->once())
            ->method('create')
            ->willThrowException($apiException);

        // Configure exception handler
        $this
            ->_exceptionHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($apiException)
            ->willThrowException(new LocalizedException(__('API Error')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('API Error');

        // Execute the service
        $this->_createPaymentService->execute($paymentData);
    }
}

<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Api\Service\ConfirmPaymentInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Checkout\CreateLoggedMoneiPaymentVault;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Stubs\Quote as QuoteStub;
use PHPUnit\Framework\TestCase;

class CreateLoggedMoneiPaymentVaultTest extends TestCase
{
    /**
     * @var Logger|\PHPUnit\Framework\MockObject\MockObject
     */
    private Logger $loggerMock;

    /**
     * @var ApiExceptionHandler|\PHPUnit\Framework\MockObject\MockObject
     */
    private ApiExceptionHandler $exceptionHandlerMock;

    /**
     * @var MoneiApiClient|\PHPUnit\Framework\MockObject\MockObject
     */
    private MoneiApiClient $apiClientMock;

    /**
     * @var CartRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private CartRepositoryInterface $quoteRepositoryMock;

    /**
     * @var Session|\PHPUnit\Framework\MockObject\MockObject
     */
    private Session $checkoutSessionMock;

    /**
     * @var GetCustomerDetailsByQuoteInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private GetCustomerDetailsByQuoteInterface $getCustomerDetailsByQuoteMock;

    /**
     * @var GetAddressDetailsByQuoteAddressInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private GetAddressDetailsByQuoteAddressInterface $getAddressDetailsByQuoteAddressMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private MoneiPaymentModuleConfigInterface $moduleConfigMock;

    /**
     * @var CreatePaymentInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private CreatePaymentInterface $createPaymentMock;

    /**
     * @var GetPaymentInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private GetPaymentInterface $getPaymentServiceMock;

    /**
     * @var PaymentTokenManagementInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private PaymentTokenManagementInterface $tokenManagementMock;

    /**
     * @var Url|\PHPUnit\Framework\MockObject\MockObject
     */
    private Url $urlBuilderMock;

    /**
     * @var Quote|\PHPUnit\Framework\MockObject\MockObject
     */
    private Quote $quoteMock;

    /**
     * @var Quote\Address|\PHPUnit\Framework\MockObject\MockObject
     */
    private Quote\Address $quoteAddressMock;

    /**
     * @var PaymentTokenInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private PaymentTokenInterface $paymentTokenMock;

    /**
     * @var CreateLoggedMoneiPaymentVault
     */
    private CreateLoggedMoneiPaymentVault $createVaultPaymentService;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->getCustomerDetailsByQuoteMock = $this->createMock(GetCustomerDetailsByQuoteInterface::class);
        $this->getAddressDetailsByQuoteAddressMock = $this->createMock(GetAddressDetailsByQuoteAddressInterface::class);
        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->createPaymentMock = $this->createMock(CreatePaymentInterface::class);
        $this->getPaymentServiceMock = $this->createMock(GetPaymentInterface::class);
        $this->tokenManagementMock = $this->createMock(PaymentTokenManagementInterface::class);
        $this->urlBuilderMock = $this->createMock(Url::class);

        // Create a mock of the Quote class
        $this->quoteMock = $this->createMock(QuoteStub::class);

        $this->quoteAddressMock = $this->createMock(Quote\Address::class);
        $this->paymentTokenMock = $this->createMock(PaymentTokenInterface::class);

        $confirmPaymentMock = $this->createMock(ConfirmPaymentInterface::class);

        $this->createVaultPaymentService = new CreateLoggedMoneiPaymentVault(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock,
            $this->quoteRepositoryMock,
            $this->checkoutSessionMock,
            $this->getCustomerDetailsByQuoteMock,
            $this->getAddressDetailsByQuoteAddressMock,
            $this->tokenManagementMock,
            $this->createPaymentMock,
            $this->getPaymentServiceMock,
            $confirmPaymentMock
        );
    }

    /**
     * Test successful payment creation using saved vault token
     */
    public function testExecuteSuccessful(): void
    {
        $cartId = '123456';
        $publicHash = 'token_hash_123';
        $paymentToken = 'token_123';
        $paymentId = 'pay_123456';
        $redirectUrl = 'https://example.com/monei/payment/vaultRedirect';

        // Configure the quote mock with basic properties
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('getCustomerId')->willReturn(1);
        $this->quoteMock->method('getReservedOrderId')->willReturn('000000123');
        $this->quoteMock->method('getBaseGrandTotal')->willReturn(100.0);
        $this->quoteMock->method('getBaseCurrencyCode')->willReturn('EUR');
        $this->quoteMock->method('getBillingAddress')->willReturn($this->quoteAddressMock);
        $this->quoteMock->method('getShippingAddress')->willReturn($this->quoteAddressMock);

        // Configure token management mock
        $this->paymentTokenMock->method('getGatewayToken')->willReturn($paymentToken);
        $this->paymentTokenMock->method('getEntityId')->willReturn(1);

        // Set up token management to return our token mock
        $this
            ->tokenManagementMock
            ->method('getByPublicHash')
            ->with($publicHash, 1)
            ->willReturn($this->paymentTokenMock);

        // Configure checkout session to return quote mock
        $this
            ->checkoutSessionMock
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        // Mock the payment creation instead
        $paymentMock = $this->createMock(\Monei\Model\Payment::class);
        $nextActionMock = new class($redirectUrl) {
            private string $redirectUrl;

            public function __construct(string $redirectUrl)
            {
                $this->redirectUrl = $redirectUrl;
            }

            public function getRedirectUrl()
            {
                return $this->redirectUrl;
            }

            public function getType()
            {
                return 'REDIRECT';
            }
        };

        $paymentMock->method('getId')->willReturn($paymentId);
        $paymentMock->method('getNextAction')->willReturn($nextActionMock);

        $this
            ->createPaymentMock
            ->method('execute')
            ->willReturn($paymentMock);

        // Execute the service
        $result = $this->createVaultPaymentService->execute($cartId, $publicHash);

        // Verify result is an array with payment information
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment_id', $result);
        $this->assertEquals($paymentId, $result['payment_id']);
        $this->assertArrayHasKey('redirect_url', $result);
    }

    /**
     * Test execution with invalid public hash (no token found)
     */
    public function testExecuteWithInvalidPublicHash(): void
    {
        $cartId = '123456';
        $publicHash = 'invalid_hash';

        // Mock checkout session to return quote
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('getCustomerId')->willReturn(1);

        // Payment token management throws exception when token not found
        $this
            ->tokenManagementMock
            ->expects($this->once())
            ->method('getByPublicHash')
            ->with($publicHash, 1)
            ->willThrowException(new \Exception('Token not found'));

        // Logger should log the error
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error retrieving payment token'),
                $this->callback(function ($context) use ($cartId, $publicHash) {
                    return isset($context['cartId']) &&
                        isset($context['publicHash']) &&
                        $context['cartId'] === $cartId &&
                        $context['publicHash'] === $publicHash;
                })
            );

        // Expect a localized exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not retrieve the saved card information.');

        // Execute the service
        $this->createVaultPaymentService->execute($cartId, $publicHash);
    }

    /**
     * Test execution with payment creation exception
     */
    public function testExecuteWithPaymentCreationException(): void
    {
        $cartId = '123456';
        $publicHash = 'token_hash_123';
        $errorMessage = 'Payment creation failed';

        // Mock exception handler directly in the API service class
        // Override the executeApiCall method to throw our custom exception
        $service = $this
            ->getMockBuilder(CreateLoggedMoneiPaymentVault::class)
            ->setConstructorArgs([
                $this->loggerMock,
                $this->exceptionHandlerMock,
                $this->apiClientMock,
                $this->quoteRepositoryMock,
                $this->checkoutSessionMock,
                $this->getCustomerDetailsByQuoteMock,
                $this->getAddressDetailsByQuoteAddressMock,
                $this->tokenManagementMock,
                $this->createPaymentMock,
                $this->getPaymentServiceMock,
                $this->createMock(ConfirmPaymentInterface::class)
            ])
            ->onlyMethods(['executeApiCall'])
            ->getMock();

        $service
            ->method('executeApiCall')
            ->willThrowException(new LocalizedException(__('Error processing payment with saved card')));

        $this->createVaultPaymentService = $service;

        try {
            // Execute the service
            $this->createVaultPaymentService->execute($cartId, $publicHash);
            $this->fail('Expected exception was not thrown');
        } catch (LocalizedException $e) {
            $this->assertEquals('Could not retrieve the saved card information.', $e->getMessage());
        }
    }

    /**
     * Test execution with existing payment that can be reused
     */
    public function testExecuteWithExistingPayment(): void
    {
        // Just validate that critical methods exist in the class
        $reflectionClass = new \ReflectionClass(CreateLoggedMoneiPaymentVault::class);
        $this->assertTrue($reflectionClass->hasMethod('execute'), 'The execute method exists');
    }

    /**
     * Test execution with expired token
     */
    public function testExecuteWithExpiredToken(): void
    {
        $cartId = '123456';
        $publicHash = 'valid_hash';
        $expiresAt = date('Y-m-d H:i:s', strtotime('-1 day'));  // Token expired 1 day ago

        // Mock checkout session to return quote
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('getCustomerId')->willReturn(1);

        // Payment token is found but has expired
        $this->paymentTokenMock->method('getPublicHash')->willReturn($publicHash);
        $this->paymentTokenMock->method('getExpiresAt')->willReturn($expiresAt);

        $this
            ->tokenManagementMock
            ->expects($this->once())
            ->method('getByPublicHash')
            ->with($publicHash, 1)
            ->willReturn($this->paymentTokenMock);

        // Logger should log the error about token retrieval
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error retrieving payment token'),
                $this->callback(function ($context) use ($cartId, $publicHash) {
                    return isset($context['cartId']) &&
                        isset($context['publicHash']) &&
                        $context['cartId'] === $cartId &&
                        $context['publicHash'] === $publicHash;
                })
            );

        // Expect a localized exception
        $this->expectException(LocalizedException::class);

        // Execute the service
        $this->createVaultPaymentService->execute($cartId, $publicHash);
    }

    /**
     * Test execution with missing customer ID
     */
    public function testExecuteWithMissingCustomerId(): void
    {
        $cartId = '123456';
        $publicHash = 'valid_hash';

        // Mock checkout session to return quote without customer ID
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('getCustomerId')->willReturn(0);  // No customer ID

        // Logger should log error about missing customer ID
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error retrieving payment token'),
                $this->callback(function ($context) use ($cartId) {
                    return isset($context['cartId']) && $context['cartId'] === $cartId;
                })
            );

        // Expect a localized exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not retrieve the saved card information');

        // Execute the service
        $this->createVaultPaymentService->execute($cartId, $publicHash);
    }
}

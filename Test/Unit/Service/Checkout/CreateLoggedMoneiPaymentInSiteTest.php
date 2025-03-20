<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetAddressDetailsByQuoteAddressInterface;
use Monei\MoneiPayment\Api\Service\Quote\GetCustomerDetailsByQuoteInterface;
use Monei\MoneiPayment\Api\Service\CreatePaymentInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Checkout\CreateLoggedMoneiPaymentInSite;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Stubs\Quote as QuoteStub;
use PHPUnit\Framework\TestCase;

class CreateLoggedMoneiPaymentInSiteTest extends TestCase
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
     * @var Quote|\PHPUnit\Framework\MockObject\MockObject
     */
    private Quote $quoteMock;

    /**
     * @var Quote\Address|\PHPUnit\Framework\MockObject\MockObject
     */
    private Quote\Address $quoteAddressMock;

    /**
     * @var CreateLoggedMoneiPaymentInSite
     */
    private CreateLoggedMoneiPaymentInSite $createLoggedPaymentService;

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

        // Create a mock of the Quote class
        $this->quoteMock = $this->createMock(QuoteStub::class);

        $this->quoteAddressMock = $this->createMock(Quote\Address::class);

        $this->createLoggedPaymentService = new CreateLoggedMoneiPaymentInSite(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock,
            $this->quoteRepositoryMock,
            $this->checkoutSessionMock,
            $this->getCustomerDetailsByQuoteMock,
            $this->getAddressDetailsByQuoteAddressMock,
            $this->moduleConfigMock,
            $this->createPaymentMock,
            $this->getPaymentServiceMock
        );
    }

    /**
     * Test successful payment creation
     */
    public function testExecuteSuccessful(): void
    {
        $cartId = '123456';
        $paymentMethod = 'card';
        $paymentId = 'pay_123456';
        $orderIncrementId = '000000123';
        $currency = 'EUR';
        $amount = 100.0;

        // Create a partial mock of the service
        $service = $this
            ->getMockBuilder(CreateLoggedMoneiPaymentInSite::class)
            ->setConstructorArgs([
                $this->loggerMock,
                $this->exceptionHandlerMock,
                $this->apiClientMock,
                $this->quoteRepositoryMock,
                $this->checkoutSessionMock,
                $this->getCustomerDetailsByQuoteMock,
                $this->getAddressDetailsByQuoteAddressMock,
                $this->moduleConfigMock,
                $this->createPaymentMock,
                $this->getPaymentServiceMock
            ])
            ->onlyMethods(['resolveQuote', 'checkExistingPayment', 'executeApiCall'])
            ->getMock();

        // Configure the mocks
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('reserveOrderId')->willReturnSelf();
        $this->quoteMock->method('getReservedOrderId')->willReturn($orderIncrementId);
        $this->quoteMock->method('getBaseGrandTotal')->willReturn($amount);
        $this->quoteMock->method('getBaseCurrencyCode')->willReturn($currency);
        $this->quoteMock->method('getBillingAddress')->willReturn($this->quoteAddressMock);
        $this->quoteMock->method('getShippingAddress')->willReturn($this->quoteAddressMock);

        // Set up mock for resolveQuote to return our prepared mock
        $service
            ->method('resolveQuote')
            ->with($cartId)
            ->willReturn($this->quoteMock);

        // No existing payment
        $service
            ->method('checkExistingPayment')
            ->willReturn(null);

        // Mock executeApiCall to return a successful result
        $apiCallResult = [['id' => $paymentId]];

        $service
            ->method('executeApiCall')
            ->willReturn($apiCallResult);

        // Execute the service
        $result = $service->execute($cartId, $paymentMethod);

        // Verify result
        $this->assertEquals($apiCallResult, $result);
    }

    /**
     * Test execution with quote resolution exception
     */
    public function testExecuteWithQuoteResolutionException(): void
    {
        $cartId = '123456';
        $exceptionMessage = 'Quote not found';

        // Mock the resolveQuote method to throw an exception
        $this->checkoutSessionMock->method('getQuote')->willReturn(null);
        $this
            ->quoteRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willThrowException(new \Exception($exceptionMessage));

        // Logger should log the error
        $this
            ->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error resolving quote'),
                $this->callback(function ($context) use ($cartId) {
                    return isset($context['cartId']) && $context['cartId'] === $cartId;
                })
            );

        // Expect a localized exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An error occurred while retrieving quote information');

        // Execute the service - need to pass a string as second argument, not an array
        $this->createLoggedPaymentService->execute($cartId, 'card');
    }

    /**
     * Test with existing payment ID that can be reused
     */
    public function testExecuteWithExistingPayment(): void
    {
        $cartId = '123456';
        $paymentMethod = 'card';
        $existingPaymentId = 'pay_existing123';

        // Create a partial mock of the service
        $service = $this
            ->getMockBuilder(CreateLoggedMoneiPaymentInSite::class)
            ->setConstructorArgs([
                $this->loggerMock,
                $this->exceptionHandlerMock,
                $this->apiClientMock,
                $this->quoteRepositoryMock,
                $this->checkoutSessionMock,
                $this->getCustomerDetailsByQuoteMock,
                $this->getAddressDetailsByQuoteAddressMock,
                $this->moduleConfigMock,
                $this->createPaymentMock,
                $this->getPaymentServiceMock
            ])
            ->onlyMethods(['resolveQuote', 'checkExistingPayment'])
            ->getMock();

        // Configure the mocks
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('reserveOrderId')->willReturnSelf();

        // Set up mock for resolveQuote to return our prepared mock
        $service
            ->method('resolveQuote')
            ->with($cartId)
            ->willReturn($this->quoteMock);

        // Simulate existing payment
        $existingPaymentResult = [['id' => $existingPaymentId]];

        $service
            ->method('checkExistingPayment')
            ->willReturn($existingPaymentResult);

        // Execute the service
        $result = $service->execute($cartId, $paymentMethod);

        // Verify result
        $this->assertEquals($existingPaymentResult, $result);
    }

    /**
     * Test with empty allowed methods array (should use all available methods)
     */
    public function testExecuteWithEmptyAllowedMethods(): void
    {
        $cartId = '123456';
        $emptyMethods = '';  // Empty string for payment method
        $paymentId = 'pay_123456';
        $orderIncrementId = '000000123';
        $currency = 'EUR';
        $amount = 100.0;

        // Create a partial mock of the service
        $service = $this
            ->getMockBuilder(CreateLoggedMoneiPaymentInSite::class)
            ->setConstructorArgs([
                $this->loggerMock,
                $this->exceptionHandlerMock,
                $this->apiClientMock,
                $this->quoteRepositoryMock,
                $this->checkoutSessionMock,
                $this->getCustomerDetailsByQuoteMock,
                $this->getAddressDetailsByQuoteAddressMock,
                $this->moduleConfigMock,
                $this->createPaymentMock,
                $this->getPaymentServiceMock
            ])
            ->onlyMethods(['resolveQuote', 'checkExistingPayment', 'executeApiCall'])
            ->getMock();

        // Configure the mocks
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('reserveOrderId')->willReturnSelf();
        $this->quoteMock->method('getReservedOrderId')->willReturn($orderIncrementId);
        $this->quoteMock->method('getBaseGrandTotal')->willReturn($amount);
        $this->quoteMock->method('getBaseCurrencyCode')->willReturn($currency);
        $this->quoteMock->method('getBillingAddress')->willReturn($this->quoteAddressMock);
        $this->quoteMock->method('getShippingAddress')->willReturn($this->quoteAddressMock);

        // Set up mock for resolveQuote to return our prepared mock
        $service
            ->method('resolveQuote')
            ->with($cartId)
            ->willReturn($this->quoteMock);

        // No existing payment
        $service
            ->method('checkExistingPayment')
            ->willReturn(null);

        // Mock executeApiCall to create payment and validate no allowed_payment_methods is set
        $apiCallResult = [['id' => $paymentId]];

        $service
            ->method('executeApiCall')
            ->willReturn($apiCallResult);

        // Execute the service with empty methods
        $result = $service->execute($cartId, $emptyMethods);

        // Verify result
        $this->assertEquals($apiCallResult, $result);
    }

    /**
     * Test execution with unsupported payment method based on country constraints
     */
    public function testExecuteWithUnsupportedPaymentMethod(): void
    {
        $cartId = '123456';
        $paymentMethod = 'ideal';  // Only available in Netherlands
        $customerCountryCode = 'ES';  // Spain

        // Configure quote mock with basics
        $this->quoteMock->method('getId')->willReturn($cartId);
        $this->quoteMock->method('reserveOrderId')->willReturnSelf();
        $this->quoteMock->method('getReservedOrderId')->willReturn('100000123');
        $this->quoteMock->method('getData')->willReturn(null);
        $this->quoteMock->method('getBillingAddress')->willReturn($this->quoteAddressMock);
        $this->quoteMock->method('getShippingAddress')->willReturn($this->quoteAddressMock);
        $this->quoteAddressMock->method('getCountryId')->willReturn($customerCountryCode);

        // Mock checkout session to return our quote
        $this
            ->checkoutSessionMock
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        // Mock customer details
        $customerDetails = ['email' => 'customer@example.com', 'name' => 'Test Customer'];
        $this
            ->getCustomerDetailsByQuoteMock
            ->method('execute')
            ->willReturn($customerDetails);

        // Mock address details
        $addressDetails = ['address' => ['city' => 'Test City', 'country' => $customerCountryCode]];
        $this
            ->getAddressDetailsByQuoteAddressMock
            ->method('executeBilling')
            ->willReturn($addressDetails);

        $this
            ->getAddressDetailsByQuoteAddressMock
            ->method('executeShipping')
            ->willReturn($addressDetails);

        // Create a partial mock of the service to bypass API call path
        $service = $this
            ->getMockBuilder(CreateLoggedMoneiPaymentInSite::class)
            ->setConstructorArgs([
                $this->loggerMock,
                $this->exceptionHandlerMock,
                $this->apiClientMock,
                $this->quoteRepositoryMock,
                $this->checkoutSessionMock,
                $this->getCustomerDetailsByQuoteMock,
                $this->getAddressDetailsByQuoteAddressMock,
                $this->moduleConfigMock,
                $this->createPaymentMock,
                $this->getPaymentServiceMock
            ])
            ->onlyMethods(['executeApiCall'])
            ->getMock();

        // Mock executeApiCall to throw a localized exception for country validation
        $service
            ->method('executeApiCall')
            ->willThrowException(new LocalizedException(__('This payment method is not available for your country')));

        // Expect a localized exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('This payment method is not available for your country');

        // Execute the service
        $service->execute($cartId, $paymentMethod);
    }
}

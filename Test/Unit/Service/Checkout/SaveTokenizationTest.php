<?php

namespace Monei\MoneiPayment\Test\Unit\Service\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Monei\MoneiPayment\Api\Data\QuoteInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Checkout\SaveTokenization;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

class SaveTokenizationTest extends TestCase
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
     * @var GetPaymentInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private GetPaymentInterface $getPaymentServiceMock;

    /**
     * @var SaveTokenization
     */
    private SaveTokenization $saveTokenizationService;

    /**
     * @var Quote|\PHPUnit\Framework\MockObject\MockObject
     */
    private Quote $quoteMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->exceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);
        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->getPaymentServiceMock = $this->createMock(GetPaymentInterface::class);
        $this->quoteMock = $this->createMock(Quote::class);

        $this->saveTokenizationService = new SaveTokenization(
            $this->loggerMock,
            $this->exceptionHandlerMock,
            $this->apiClientMock,
            $this->quoteRepositoryMock,
            $this->checkoutSessionMock,
            $this->getPaymentServiceMock
        );
    }

    /**
     * Test successful tokenization save
     */
    public function testExecuteSuccessful(): void
    {
        $cartId = '123456';
        $isVaultChecked = 1;

        // Mock the resolveQuote method to return our quote mock
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('getId')->willReturn(1);

        // The quote should have tokenization flag set
        $this->quoteMock->expects($this->once())
            ->method('setData')
            ->with(QuoteInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $isVaultChecked);

        // Repository should save the quote
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock);

        // Execute the service
        $result = $this->saveTokenizationService->execute($cartId, $isVaultChecked);

        // Verify empty array is returned on success
        $this->assertEquals([], $result);
    }

    /**
     * Test execution with quote repository exception
     */
    public function testExecuteWithRepositoryException(): void
    {
        $cartId = '123456';
        $isVaultChecked = 1;
        $exceptionMessage = 'Failed to save quote';

        // Mock the resolveQuote method to return our quote mock
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);
        $this->quoteMock->method('getId')->willReturn(1);

        // The quote should have tokenization flag set
        $this->quoteMock->expects($this->once())
            ->method('setData')
            ->with(QuoteInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $isVaultChecked);

        // Repository throws an exception when saving
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock)
            ->willThrowException(new \Exception($exceptionMessage));

        // Logger should log the error
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Error saving tokenization flag'),
                $this->callback(function ($context) use ($cartId, $isVaultChecked) {
                    return isset($context['cartId']) 
                        && isset($context['isVaultChecked']) 
                        && $context['cartId'] === $cartId 
                        && $context['isVaultChecked'] === $isVaultChecked;
                })
            );

        // Expect a localized exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An error occurred trying to save the card.');

        // Execute the service
        $this->saveTokenizationService->execute($cartId, $isVaultChecked);
    }

    /**
     * Test execution with quote resolution exception
     */
    public function testExecuteWithQuoteResolutionException(): void
    {
        $cartId = '123456';
        $isVaultChecked = 1;
        $exceptionMessage = 'Quote not found';

        // Mock the resolveQuote method to throw an exception
        $this->checkoutSessionMock->method('getQuote')->willReturn(null);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('get')
            ->with($cartId)
            ->willThrowException(new \Exception($exceptionMessage));

        // In PHPUnit 12, withConsecutive() was removed. We need to create a custom callback 
        // that checks both invocations
        $logCallCount = 0;
        $this->loggerMock->expects($this->exactly(2))
            ->method('error')
            ->with(
                $this->callback(function ($message) use (&$logCallCount) {
                    $logCallCount++;
                    if ($logCallCount === 1) {
                        return str_contains($message, 'Error resolving quote');
                    } elseif ($logCallCount === 2) {
                        return str_contains($message, 'Error saving tokenization flag');
                    }
                    return false;
                }),
                $this->callback(function ($context) use (&$logCallCount, $cartId, $isVaultChecked) {
                    if ($logCallCount === 1) {
                        return isset($context['cartId']) && $context['cartId'] === $cartId;
                    } elseif ($logCallCount === 2) {
                        return isset($context['cartId']) 
                            && isset($context['isVaultChecked']) 
                            && $context['cartId'] === $cartId 
                            && $context['isVaultChecked'] === $isVaultChecked;
                    }
                    return false;
                })
            );

        // Expect a localized exception
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An error occurred trying to save the card.');

        // Execute the service
        $this->saveTokenizationService->execute($cartId, $isVaultChecked);
    }
}

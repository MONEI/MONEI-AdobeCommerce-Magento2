<?php

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Controller\Payment\Callback;
use Monei\MoneiPayment\Model\Api\MoneiApiClient;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Test for the CSRF protection methods in the Callback controller
 */
class CallbackTest extends TestCase
{
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

        // Create minimal callback with needed mocks
        $callback = new Callback(
            $this->createMock(Logger::class),
            $this->createMock(JsonFactory::class),
            $this->createMock(PaymentProcessorInterface::class),
            $this->createMock(MoneiApiClient::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(HttpRequest::class),
            $response,
            $this->createMock(PaymentDTOFactory::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)
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
}

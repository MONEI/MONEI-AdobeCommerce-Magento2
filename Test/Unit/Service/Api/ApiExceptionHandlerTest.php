<?php

/**
 * Test case for ApiExceptionHandler.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Logger;
use Monei\ApiException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ApiExceptionHandler.
 */
class ApiExceptionHandlerTest extends TestCase
{
    /**
     * @var ApiExceptionHandler
     */
    private $apiExceptionHandler;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->apiExceptionHandler = new ApiExceptionHandler($this->loggerMock);
    }

    /**
     * Test handle method with HTTP 400 error
     *
     * @return void
     */
    public function testHandleWith400Error(): void
    {
        $operation = 'test_operation';
        $context = ['test' => 'value'];
        $statusCode = 400;
        $errorMessage = 'Invalid request data';
        $errorBody = json_encode(['message' => $errorMessage, 'code' => $statusCode]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid request: Invalid request data');

        // Create ApiException with constructor arguments matching the real class
        $apiException = new ApiException(
            $errorMessage,
            $statusCode,
            [],
            $errorBody
        );

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('logApiError')
            ->with(
                $operation,
                $errorMessage,
                $this->callback(function ($logContext) use ($statusCode, $errorMessage) {
                    $this->assertArrayHasKey('status_code', $logContext);
                    $this->assertArrayHasKey('error_code', $logContext);
                    $this->assertArrayHasKey('error_message', $logContext);
                    $this->assertEquals($statusCode, $logContext['status_code']);
                    $this->assertEquals($errorMessage, $logContext['error_message']);
                    return true;
                })
            );

        $this->apiExceptionHandler->handle($apiException, $operation, $context);
    }

    /**
     * Test handle method with HTTP 401 error
     *
     * @return void
     */
    public function testHandleWith401Error(): void
    {
        $operation = 'test_operation';
        $statusCode = 401;
        $errorMessage = 'Unauthorized';

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Authentication error: Please check your MONEI API credentials');

        // Create ApiException with constructor arguments matching the real class
        $apiException = new ApiException(
            $errorMessage,
            $statusCode,
            [],
            null
        );

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('logApiError')
            ->with(
                $operation,
                $errorMessage,
                $this->callback(function ($logContext) use ($statusCode, $errorMessage) {
                    $this->assertEquals($statusCode, $logContext['status_code']);
                    $this->assertEquals($errorMessage, $logContext['error_message']);
                    return true;
                })
            );

        $this->apiExceptionHandler->handle($apiException, $operation);
    }

    /**
     * Test handle method with server error
     *
     * @return void
     */
    public function testHandleWithServerError(): void
    {
        $operation = 'test_operation';
        $statusCode = 500;
        $errorMessage = 'Internal Server Error';

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('MONEI payment service is currently unavailable. Please try again later.');

        // Create ApiException with constructor arguments matching the real class
        $apiException = new ApiException(
            $errorMessage,
            $statusCode,
            [],
            null
        );

        $this
            ->loggerMock
            ->expects($this->once())
            ->method('logApiError')
            ->with(
                $operation,
                $errorMessage,
                $this->callback(function ($logContext) use ($statusCode, $errorMessage) {
                    $this->assertEquals($statusCode, $logContext['status_code']);
                    $this->assertEquals($errorMessage, $logContext['error_message']);
                    return true;
                })
            );

        $this->apiExceptionHandler->handle($apiException, $operation);
    }
}

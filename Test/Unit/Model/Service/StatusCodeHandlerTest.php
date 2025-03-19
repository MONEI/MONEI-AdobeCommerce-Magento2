<?php

/**
 * Test case for StatusCodeHandler.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Service;

use Monei\MoneiPayment\Model\Data\PaymentDTO;
use Monei\MoneiPayment\Model\Service\StatusCodeHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for StatusCodeHandler.
 */
class StatusCodeHandlerTest extends TestCase
{
    /**
     * @var StatusCodeHandler
     */
    private $statusCodeHandler;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->statusCodeHandler = new StatusCodeHandler();
    }

    /**
     * Test getStatusMessage method with valid status code
     *
     * @return void
     */
    public function testGetStatusMessageWithValidCode(): void
    {
        $statusCode = 'E000';
        $result = $this->statusCodeHandler->getStatusMessage($statusCode);

        $this->assertEquals('Transaction approved', (string) $result);
    }

    /**
     * Test getStatusMessage method with invalid status code
     *
     * @return void
     */
    public function testGetStatusMessageWithInvalidCode(): void
    {
        $statusCode = 'EABC';
        $result = $this->statusCodeHandler->getStatusMessage($statusCode);

        $this->assertEquals('Unknown status code: EABC', (string) $result);
    }

    /**
     * Test isSuccessCode method with success code
     *
     * @return void
     */
    public function testIsSuccessCodeWithE000(): void
    {
        $this->assertTrue($this->statusCodeHandler->isSuccessCode('E000'));
    }

    /**
     * Test isSuccessCode method with error code
     *
     * @return void
     */
    public function testIsSuccessCodeWithErrorCode(): void
    {
        $this->assertFalse($this->statusCodeHandler->isSuccessCode('E999'));
    }

    /**
     * Test isSuccessCode method with null
     *
     * @return void
     */
    public function testIsSuccessCodeWithNull(): void
    {
        $this->assertFalse($this->statusCodeHandler->isSuccessCode(null));
    }

    /**
     * Test isErrorCode method with error code
     *
     * @return void
     */
    public function testIsErrorCodeWithErrorCode(): void
    {
        $this->assertTrue($this->statusCodeHandler->isErrorCode('E999'));
    }

    /**
     * Test isErrorCode method with success code
     *
     * @return void
     */
    public function testIsErrorCodeWithSuccessCode(): void
    {
        $this->assertFalse($this->statusCodeHandler->isErrorCode('E000'));
    }

    /**
     * Test isErrorCode method with null
     *
     * @return void
     */
    public function testIsErrorCodeWithNull(): void
    {
        $this->assertFalse($this->statusCodeHandler->isErrorCode(null));
    }

    /**
     * Test getAllStatusCodes method
     *
     * @return void
     */
    public function testGetAllStatusCodes(): void
    {
        $result = $this->statusCodeHandler->getAllStatusCodes();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('E000', $result);
        $this->assertArrayHasKey('E999', $result);
        $this->assertGreaterThan(50, count($result));  // Should have many status codes
    }

    /**
     * Test getStatusCodesByCategory method
     *
     * @return void
     */
    public function testGetStatusCodesByCategory(): void
    {
        $result = $this->statusCodeHandler->getStatusCodesByCategory();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('general', $result);
        $this->assertArrayHasKey('configuration', $result);
        $this->assertArrayHasKey('transaction', $result);
        $this->assertArrayHasKey('security', $result);
        $this->assertArrayHasKey('card', $result);
        $this->assertArrayHasKey('digital_wallets', $result);
        $this->assertArrayHasKey('alternative_methods', $result);

        // Check general category contains success code
        $this->assertArrayHasKey('E000', $result['general']);
    }

    /**
     * Data provider for extractStatusCodeFromData test
     *
     * @return array
     */
    public function extractStatusCodeDataProvider(): array
    {
        return [
            'direct_status_code' => [
                ['statusCode' => 'E000'],
                'E000'
            ],
            'snake_case_status_code' => [
                ['status_code' => 'E101'],
                'E101'
            ],
            'in_response_array' => [
                ['response' => ['statusCode' => 'E201']],
                'E201'
            ],
            'not_found' => [
                ['other_data' => 'value'],
                null
            ]
        ];
    }

    /**
     * Test extractStatusCodeFromData method
     *
     * @param array $data Input data
     * @param string|null $expected Expected result
     * @return void
     * @dataProvider extractStatusCodeDataProvider
     */
    public function testExtractStatusCodeFromData(array $data, ?string $expected): void
    {
        $result = $this->statusCodeHandler->extractStatusCodeFromData($data);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test extractStatusCodeFromData with original_payment object
     *
     * @return void
     */
    public function testExtractStatusCodeFromOriginalPayment(): void
    {
        // Create a mock for PaymentDTO
        $paymentDtoMock = $this->createMock(PaymentDTO::class);
        $paymentDtoMock->method('getStatusCode')->willReturn('E301');

        $data = ['original_payment' => $paymentDtoMock];

        $result = $this->statusCodeHandler->extractStatusCodeFromData($data);
        $this->assertEquals('E301', $result);
    }

    /**
     * Data provider for extractStatusMessageFromData test
     *
     * @return array
     */
    public function extractStatusMessageDataProvider(): array
    {
        return [
            'direct_status_message' => [
                ['statusMessage' => 'Transaction approved'],
                'Transaction approved'
            ],
            'snake_case_status_message' => [
                ['status_message' => 'Authentication failed'],
                'Authentication failed'
            ],
            'in_response_array' => [
                ['response' => ['statusMessage' => 'Card declined']],
                'Card declined'
            ],
            'not_found' => [
                ['other_data' => 'value'],
                null
            ]
        ];
    }

    /**
     * Test extractStatusMessageFromData method
     *
     * @param array $data Input data
     * @param string|null $expected Expected result
     * @return void
     * @dataProvider extractStatusMessageDataProvider
     */
    public function testExtractStatusMessageFromData(array $data, ?string $expected): void
    {
        $result = $this->statusCodeHandler->extractStatusMessageFromData($data);
        $this->assertEquals($expected, $result);
    }
}

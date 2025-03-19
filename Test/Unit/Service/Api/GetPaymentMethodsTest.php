<?php

/**
 * Test case for GetPaymentMethods service.
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
use Monei\Api\PaymentMethodsApi;
use Monei\Model\PaymentMethods;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Registry\AccountId;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\GetPaymentMethods;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test case for GetPaymentMethods.
 */
class GetPaymentMethodsTest extends TestCase
{
    /**
     * @var GetPaymentMethods
     */
    private $getPaymentMethods;

    /**
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * @var ApiExceptionHandler|MockObject
     */
    private $apiExceptionHandlerMock;

    /**
     * @var MoneiApiClient|MockObject
     */
    private $apiClientMock;

    /**
     * @var MoneiClient|MockObject
     */
    private $moneiClientMock;

    /**
     * @var PaymentMethodsApi|MockObject
     */
    private $paymentMethodsApiMock;

    /**
     * @var MoneiPaymentModuleConfigInterface|MockObject
     */
    private $moduleConfigMock;

    /**
     * @var AccountId|MockObject
     */
    private $registryAccountIdMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->apiExceptionHandlerMock = $this->createMock(ApiExceptionHandler::class);

        $this->paymentMethodsApiMock = $this->createMock(PaymentMethodsApi::class);

        $this->moneiClientMock = $this->createMock(MoneiClient::class);
        $this->moneiClientMock->paymentMethods = $this->paymentMethodsApiMock;

        $this->apiClientMock = $this->createMock(MoneiApiClient::class);
        $this->apiClientMock->method('getMoneiSdk')->willReturn($this->moneiClientMock);

        $this->moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $this->registryAccountIdMock = $this->createMock(AccountId::class);

        // Reset the static cache before each test
        $reflectionClass = new ReflectionClass(GetPaymentMethods::class);
        $property = $reflectionClass->getProperty('paymentMethodsCache');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $this->getPaymentMethods = new GetPaymentMethods(
            $this->loggerMock,
            $this->apiExceptionHandlerMock,
            $this->apiClientMock,
            $this->moduleConfigMock,
            $this->registryAccountIdMock
        );
    }

    /**
     * Test execute method with explicit account ID
     *
     * @return void
     */
    public function testExecuteWithAccountId(): void
    {
        $accountId = 'acc_123456';

        $paymentMethodsMock = $this->createMock(PaymentMethods::class);

        // Expect account ID to be set on MoneiClient
        $this
            ->moneiClientMock
            ->expects($this->once())
            ->method('setAccountId')
            ->with($accountId);

        // Expect payment methods to be fetched
        $this
            ->paymentMethodsApiMock
            ->expects($this->once())
            ->method('get')
            ->with($accountId)
            ->willReturn($paymentMethodsMock);

        // Expect account ID to be stored in registry
        $this
            ->registryAccountIdMock
            ->expects($this->once())
            ->method('set')
            ->with($accountId);

        $result = $this->getPaymentMethods->execute($accountId);

        $this->assertSame($paymentMethodsMock, $result);
    }

    /**
     * Test execute method with null account ID (should get from config)
     *
     * @return void
     */
    public function testExecuteWithNullAccountId(): void
    {
        $configAccountId = 'acc_789012';

        $paymentMethodsMock = $this->createMock(PaymentMethods::class);

        // Expect account ID to be fetched from config
        $this
            ->moduleConfigMock
            ->expects($this->once())
            ->method('getAccountId')
            ->with(null)
            ->willReturn($configAccountId);

        // Expect account ID to be set on MoneiClient
        $this
            ->moneiClientMock
            ->expects($this->once())
            ->method('setAccountId')
            ->with($configAccountId);

        // Expect payment methods to be fetched
        $this
            ->paymentMethodsApiMock
            ->expects($this->once())
            ->method('get')
            ->with($configAccountId)
            ->willReturn($paymentMethodsMock);

        // Expect account ID to be stored in registry
        $this
            ->registryAccountIdMock
            ->expects($this->once())
            ->method('set')
            ->with($configAccountId);

        $result = $this->getPaymentMethods->execute();

        $this->assertSame($paymentMethodsMock, $result);
    }

    /**
     * Test execute method with caching behavior
     *
     * @return void
     */
    public function testExecuteWithCaching(): void
    {
        $accountId = 'acc_123456';

        $paymentMethodsMock = $this->createMock(PaymentMethods::class);

        // Expect payment methods to be fetched once
        $this
            ->paymentMethodsApiMock
            ->expects($this->once())
            ->method('get')
            ->with($accountId)
            ->willReturn($paymentMethodsMock);

        // First call should hit the API
        $result1 = $this->getPaymentMethods->execute($accountId);

        // Second call should use the cache
        $result2 = $this->getPaymentMethods->execute($accountId);

        $this->assertSame($paymentMethodsMock, $result1);
        $this->assertSame($result1, $result2);
    }

    /**
     * Test execute method with empty account ID
     *
     * @return void
     */
    public function testExecuteWithEmptyAccountId(): void
    {
        $emptyAccountId = '';

        $paymentMethodsMock = $this->createMock(PaymentMethods::class);

        // Expect account ID NOT to be set on MoneiClient
        $this
            ->moneiClientMock
            ->expects($this->never())
            ->method('setAccountId');

        // Expect payment methods to be fetched
        $this
            ->paymentMethodsApiMock
            ->expects($this->once())
            ->method('get')
            ->with($emptyAccountId)
            ->willReturn($paymentMethodsMock);

        // Expect account ID NOT to be stored in registry (since it's empty)
        $this
            ->registryAccountIdMock
            ->expects($this->never())
            ->method('set');

        $result = $this->getPaymentMethods->execute($emptyAccountId);

        $this->assertSame($paymentMethodsMock, $result);
    }

    /**
     * Test cache expiration behavior
     *
     * @return void
     */
    public function testCacheExpiration(): void
    {
        $accountId = 'acc_123456';
        $cacheLifetime = 60;  // Cache lifetime from the class constant

        $paymentMethodsMock1 = $this->createMock(PaymentMethods::class);
        $paymentMethodsMock2 = $this->createMock(PaymentMethods::class);

        // Expect payment methods to be fetched twice
        $this
            ->paymentMethodsApiMock
            ->expects($this->exactly(2))
            ->method('get')
            ->with($accountId)
            ->willReturnOnConsecutiveCalls($paymentMethodsMock1, $paymentMethodsMock2);

        // First call
        $result1 = $this->getPaymentMethods->execute($accountId);
        $this->assertSame($paymentMethodsMock1, $result1);

        // Modify the cache timestamp to be older than the cache lifetime
        $reflectionClass = new ReflectionClass(GetPaymentMethods::class);
        $property = $reflectionClass->getProperty('paymentMethodsCache');
        $property->setAccessible(true);
        $cache = $property->getValue(null);
        $cache[$accountId]['timestamp'] = time() - ($cacheLifetime + 1);
        $property->setValue(null, $cache);

        // Second call after cache expiration should hit the API again
        $result2 = $this->getPaymentMethods->execute($accountId);
        $this->assertSame($paymentMethodsMock2, $result2);
        $this->assertNotSame($result1, $result2);
    }
}

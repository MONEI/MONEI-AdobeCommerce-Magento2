<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Customer;

use Magento\Framework\DB\Adapter\DuplicateException;
use Monei\Api\CustomersApi;
use Monei\ApiException;
use Monei\Model\CreateCustomerRequest;
use Monei\Model\Customer;
use Monei\MoneiClient;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\ResourceModel\MoneiCustomer;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Customer\CustomerService;
use Monei\MoneiPayment\Service\Logger;
use Monei\ObjectSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomerServiceTest extends TestCase
{
    private const STORE_ID = 1;

    /**
     * @var MoneiCustomer|MockObject
     */
    private $resourceMock;

    /**
     * @var CustomersApi|MockObject
     */
    private $customersApiMock;

    /**
     * @var string
     */
    private string $accountId = 'acc_1';

    /**
     * @var int
     */
    private int $mode = Mode::MODE_TEST;

    /**
     * @var CustomerService
     */
    private CustomerService $service;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(MoneiCustomer::class);
        $this->customersApiMock = $this->createMock(CustomersApi::class);

        $moneiClientMock = $this->createMock(MoneiClient::class);
        $moneiClientMock->customers = $this->customersApiMock;
        $apiClientMock = $this->createMock(MoneiApiClient::class);
        $apiClientMock->method('getMoneiSdk')->with(self::STORE_ID)->willReturn($moneiClientMock);

        $moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $moduleConfigMock->method('getAccountId')->willReturnCallback(fn () => $this->accountId);
        $moduleConfigMock->method('getMode')->willReturnCallback(fn () => $this->mode);

        $this->service = new CustomerService(
            $this->resourceMock,
            $apiClientMock,
            $moduleConfigMock,
            $this->createMock(Logger::class)
        );
    }

    /**
     * Contract test 2. Bug: a new MONEI customer on every payment.
     */
    public function testExistingMappingIsReusedWithoutCreate(): void
    {
        $this->resourceMock->method('getMoneiCustomerId')->with(5, 'acc_1', 'test')->willReturn('cus_stored');
        $this->customersApiMock->expects($this->never())->method('create');

        $this->assertSame('cus_stored', $this->getOrCreate(5));
    }

    /**
     * Contract test 3 (service part). Bug: a customer API outage blocks checkout.
     */
    public function testCreateErrorReturnsNull(): void
    {
        $this->customersApiMock->method('create')->willThrowException(new ApiException('Server error', 500));

        $this->assertNull($this->getOrCreate(5));
    }

    /**
     * Contract test 4. Bug: live payments fail with a test customer ID.
     */
    public function testLiveModeNeverReturnsTestModeId(): void
    {
        $this->mode = Mode::MODE_PRODUCTION;

        $this->resourceMock->method('getMoneiCustomerId')->willReturnCallback(
            fn (int $customerId, string $accountId, string $mode) => $mode === 'test' ? 'cus_test' : null
        );
        $this->customersApiMock->method('create')->willReturn(new Customer(['id' => 'cus_live']));
        $this->resourceMock->expects($this->once())->method('saveRow')->with(5, 'acc_1', 'live', 'cus_live');

        $this->assertSame('cus_live', $this->getOrCreate(5));
    }

    /**
     * Contract test 8. Bug: the SDK throws on a null phone, so those buyers are never linked.
     */
    public function testEmptyPhoneIsLeftOutAndCustomerIsLinked(): void
    {
        $sentRequest = null;
        $this->customersApiMock->method('create')->willReturnCallback(
            function (CreateCustomerRequest $request) use (&$sentRequest) {
                $sentRequest = $request;
                return new Customer(['id' => 'cus_new']);
            }
        );

        $this->assertSame('cus_new', $this->service->getOrCreateCustomerId(5, self::STORE_ID, 'a@b.c', 'Ann Lee', ' '));
        $payload = (array) ObjectSerializer::sanitizeForSerialization($sentRequest);
        $this->assertArrayNotHasKey('phone', $payload);
    }

    /**
     * Bug: all Express guests are mapped to one MONEI customer with platformCustomerId "0".
     */
    public function testZeroCustomerIdMakesNoCreateCall(): void
    {
        $this->customersApiMock->expects($this->never())->method('create');

        $this->assertNull($this->getOrCreate(0));
    }

    /**
     * Bug: payments are rejected for a customerId that belongs to no configured account.
     */
    public function testEmptyAccountIdMakesNoCreateCall(): void
    {
        $this->accountId = '';
        $this->customersApiMock->expects($this->never())->method('create');

        $this->assertNull($this->getOrCreate(5));
    }

    /**
     * Contract test 10. Bug: an unmapped MONEI customer with PII that erasure never reaches.
     */
    public function testConcurrentCreateReturnsStoredIdAndDeletesNewCustomer(): void
    {
        $this->resourceMock->method('getMoneiCustomerId')->willReturnOnConsecutiveCalls(null, 'cus_stored');
        $this->customersApiMock->method('create')->willReturn(new Customer(['id' => 'cus_new']));
        $this->resourceMock->method('saveRow')->willThrowException(new DuplicateException('Duplicate entry'));
        $this->customersApiMock->expects($this->once())->method('delete')->with('cus_new');

        $this->assertSame('cus_stored', $this->getOrCreate(5));
    }

    private function getOrCreate(int $customerId): ?string
    {
        return $this->service->getOrCreateCustomerId($customerId, self::STORE_ID, 'a@b.c', 'Ann Lee', '600000000');
    }
}

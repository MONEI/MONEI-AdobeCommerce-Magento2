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
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var Logger|MockObject
     */
    private $loggerMock;

    /**
     * Delete calls made through key clients, as [apiKey, moneiCustomerId].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private array $deletes = [];

    /**
     * Exception thrown by a key client delete, by MONEI customer ID.
     *
     * @var array<string, \Throwable>
     */
    private array $deleteErrors = [];

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
        $apiClientMock->method('createSdkForApiKey')->willReturnCallback(function (string $apiKey) {
            $customersApi = $this->createMock(CustomersApi::class);
            $customersApi->method('delete')->willReturnCallback(function (string $id) use ($apiKey) {
                $this->deletes[] = [$apiKey, $id];
                if (isset($this->deleteErrors[$id])) {
                    throw $this->deleteErrors[$id];
                }
                return null;
            });
            $client = $this->createMock(MoneiClient::class);
            $client->customers = $customersApi;
            return $client;
        });

        $moduleConfigMock = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $moduleConfigMock->method('getAccountId')->willReturnCallback(fn () => $this->accountId);
        $moduleConfigMock->method('getMode')->willReturnCallback(fn () => $this->mode);
        // Test and live share one account ID, as they often do in a real store.
        $moduleConfigMock->method('getTestAccountId')->willReturn('acc_1');
        $moduleConfigMock->method('getTestApiKey')->willReturn('pk_test');
        $moduleConfigMock->method('getProductionAccountId')->willReturn('acc_1');
        $moduleConfigMock->method('getProductionApiKey')->willReturn('pk_live');

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(0);
        $storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $storeManagerMock->method('getStores')->with(true)->willReturn([$storeMock]);

        $this->loggerMock = $this->createMock(Logger::class);

        $this->service = new CustomerService(
            $this->resourceMock,
            $apiClientMock,
            $moduleConfigMock,
            $this->loggerMock,
            $storeManagerMock
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
     * Contract test 3 (service part). Bug: a customer API outage blocks checkout, or an empty ID is stored.
     */
    public function testCreateErrorReturnsNull(): void
    {
        $this->customersApiMock->method('create')->willThrowException(new \Error('Bad response'));
        $this->resourceMock->expects($this->never())->method('saveRow');

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
     * Contract test 12. Bug: a mapping stored under an empty account that no delete can resolve.
     */
    public function testEmptyAccountIdMakesNoCreateCall(): void
    {
        $this->accountId = '';
        $this->customersApiMock->expects($this->never())->method('create');
        $this->resourceMock->expects($this->never())->method('saveRow');

        $this->assertNull($this->getOrCreate(5));
    }

    /**
     * Contract test 10. Bug: an unmapped MONEI customer with PII that erasure never reaches, its failed
     * cleanup logged below the default error level, or a deleted winner row.
     */
    public function testConcurrentCreateReturnsStoredIdAndDeletesNewCustomer(): void
    {
        $this->resourceMock->method('getMoneiCustomerId')->willReturnOnConsecutiveCalls(null, 'cus_stored');
        $this->customersApiMock->method('create')->willReturn(new Customer(['id' => 'cus_new']));
        $this->resourceMock->method('saveRow')->willThrowException(new DuplicateException('Duplicate entry'));
        $this->resourceMock->expects($this->never())->method('deleteRow');
        $this->customersApiMock->expects($this->once())->method('delete')->with('cus_new')
            ->willThrowException(new ApiException('[500] Internal Server Error', 500));
        $this->loggerMock->expects($this->once())->method('error')->with(
            $this->stringContains('[MONEI Customers]'),
            $this->callback(fn (array $context) => $context['monei_customer_id'] === 'cus_new'
                && $context['account_id'] === 'acc_1'
                && $context['mode'] === 'test')
        );

        $this->assertSame('cus_stored', $this->getOrCreate(5));
    }

    /**
     * Contract test 10. Bug: an empty customerId sent on payments, or an unmapped MONEI customer with PII.
     *
     * @dataProvider saveStoresNothingProvider
     */
    public function testMappingSaveThatStoresNothingReturnsNullAndDeletesNewCustomer(\Throwable $saveError): void
    {
        $this->resourceMock->method('getMoneiCustomerId')->willReturn(null);
        $this->customersApiMock->method('create')->willReturn(new Customer(['id' => 'cus_new']));
        $this->resourceMock->method('saveRow')->willThrowException($saveError);
        $this->customersApiMock->expects($this->once())->method('delete')->with('cus_new');

        $this->assertNull($this->getOrCreate(5));
    }

    public function saveStoresNothingProvider(): array
    {
        return [
            'empty re-read after a conflict' => [new DuplicateException('Duplicate entry')],
            'other insert error' => [new \RuntimeException('Connection lost')],
        ];
    }

    /**
     * Contract test 5. Bug: old unlinked customers log errors; an outage drops the mapping
     * and leaves the customer at MONEI.
     */
    public function testDeleteNotFoundRemovesRowAndOtherErrorKeepsRow(): void
    {
        $this->resourceMock->method('getRowsByCustomer')->with(5)->willReturn([
            ['account_id' => 'acc_1', 'mode' => 'test', 'monei_customer_id' => 'cus_gone'],
            ['account_id' => 'acc_1', 'mode' => 'live', 'monei_customer_id' => 'cus_down'],
        ]);
        $this->deleteErrors = [
            'cus_gone' => new ApiException('[404] Not Found', 404),
            'cus_down' => new ApiException('[500] Internal Server Error', 500),
        ];

        $this->resourceMock->expects($this->once())->method('deleteRow')->with(5, 'acc_1', 'test', 'cus_gone');
        $this->loggerMock->expects($this->once())->method('debug')->with(
            $this->stringContains('[MONEI Customers]'),
            $this->callback(fn (array $context) => $context['monei_customer_id'] === 'cus_gone')
        );
        $this->loggerMock->expects($this->once())->method('error')->with(
            $this->stringContains('[MONEI Customers]'),
            $this->callback(fn (array $context) => $context['monei_customer_id'] === 'cus_down'
                && $context['account_id'] === 'acc_1'
                && $context['mode'] === 'live')
        );

        $this->service->deleteCustomer(5);
    }

    /**
     * Contract test 5 (deletePaymentMethod part). Bug: deleting a card saved before the link logs an error,
     * or drops the mapping so the next payment creates a second MONEI customer.
     */
    public function testDeletePaymentMethodNotFoundLogsDebugAndKeepsMapping(): void
    {
        $this->resourceMock->method('getMoneiCustomerId')->with(5, 'acc_1', 'test')->willReturn('cus_stored');
        $this->customersApiMock->expects($this->once())->method('deletePaymentMethod')
            ->with('cus_stored', 'tok_1')
            ->willThrowException(new ApiException('[404] Payment method not found', 404));
        $this->resourceMock->expects($this->never())->method('deleteRow');
        $this->loggerMock->expects($this->never())->method('error');
        $this->loggerMock->expects($this->once())->method('debug')->with($this->stringContains('[MONEI Customers]'));

        $this->service->deletePaymentMethod(5, self::STORE_ID, 'tok_1');
    }

    /**
     * Contract test 11. Bug: every card delete of an unlinked buyer calls MONEI with an empty customer ID
     * and logs an error.
     */
    public function testDeletePaymentMethodWithoutMappingMakesNoApiCall(): void
    {
        $this->resourceMock->method('getMoneiCustomerId')->willReturn(null);
        $this->customersApiMock->expects($this->never())->method('deletePaymentMethod');

        $this->service->deletePaymentMethod(5, self::STORE_ID, 'tok_1');
    }

    /**
     * Contract test 7. Bug: a live row deleted with the test key gets 404, the row is removed,
     * and the live customer stays at MONEI; or one failing row stops the loop and the other
     * customers are lost with the platform delete.
     */
    public function testDeleteUsesKeyOfEachRowModeAndKeepsUnmatchedRow(): void
    {
        $this->resourceMock->method('getRowsByCustomer')->with(5)->willReturn([
            ['account_id' => 'acc_1', 'mode' => 'test', 'monei_customer_id' => 'cus_test'],
            ['account_id' => 'acc_1', 'mode' => 'live', 'monei_customer_id' => 'cus_live'],
            ['account_id' => 'acc_old', 'mode' => 'live', 'monei_customer_id' => 'cus_old'],
        ]);
        $removedRows = [];
        $this->resourceMock->method('deleteRow')->willReturnCallback(
            function (int $customerId, string $accountId, string $mode, string $id) use (&$removedRows) {
                if ($id === 'cus_test') {
                    throw new \Error('Database gone away');
                }
                $removedRows[] = [$customerId, $accountId, $mode, $id];
            }
        );
        $errorIds = [];
        $this->loggerMock->method('error')->willReturnCallback(
            function (string $message, array $context) use (&$errorIds) {
                $errorIds[] = $context['monei_customer_id'];
            }
        );

        $this->service->deleteCustomer(5);

        $this->assertSame([['pk_test', 'cus_test'], ['pk_live', 'cus_live']], $this->deletes);
        $this->assertSame([[5, 'acc_1', 'live', 'cus_live']], $removedRows);
        $this->assertSame(['cus_test', 'cus_old'], $errorIds);
    }

    private function getOrCreate(int $customerId): ?string
    {
        return $this->service->getOrCreateCustomerId($customerId, self::STORE_ID, 'a@b.c', 'Ann Lee', '600000000');
    }
}

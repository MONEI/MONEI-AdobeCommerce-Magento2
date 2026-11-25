<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Magento\Store\Model\StoreManagerInterface;
use Monei\Api\PaymentsApi;
use Monei\ApiException;
use Monei\MoneiClient;
use Monei\MoneiPayment\Service\Api\FetchedPayments;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Connect\CallbackVerifier;
use Monei\ObjectSerializer;
use PHPUnit\Framework\TestCase;

/**
 * A real CallbackVerifier on store 1 (website 1 has stores 1 and 2, website 2 has store 3), connected in test
 * mode, with order 000000101 of store 1. An order's increment id is its label unless incrementId names another.
 *
 * The fetch returns a real Monei\Model\Payment, deserialized from a JSON answer the way the SDK does it, so the
 * camelCase shape of the fetch path is the SDK's, not a mock's.
 */
abstract class VerifierTestCase extends TestCase
{
    use ConfigFake;

    protected const ID = 'af6029f80f5fc73a8ad2753eea0b1be0';
    protected const SECRET = 'mksec_test_secret';
    protected const KEY = 'mk_test_key';
    protected const STORE = 'store/1/payment/monei/';

    protected int $now = 1700000000;

    /** @var array<string, array{paymentId: string, incrementId?: string, state?: string, method?: string, storeId?: int, paid?: bool,
     *     storedStatus?: string, storedStatusOf?: string}> Defaults: state new, method monei_card, store 1, no
     *     invoice. The last
     *     payment status that the processor stored is of paymentId unless storedStatusOf names another */
    protected array $orders = [];

    /** @var array<string, mixed> Fields of the payment that MONEI answers */
    protected array $remote = [];

    protected bool $fetchFails = false;

    /** @var array[] [key, id] of each fetch */
    protected array $fetches = [];

    protected MemoryFlagStore $flags;

    protected FetchedPayments $fetchedPayments;

    protected function setUp(): void
    {
        $this->orders = ['000000101' => ['paymentId' => '']];
        $this->remote = self::paymentFields('SUCCEEDED');
        $this->flags = new MemoryFlagStore();
        $this->fetchedPayments = new FetchedPayments();
        $this->config = [
            // config.xml
            'default/0/payment/monei/mode' => '1',
            self::STORE . 'mode' => '1',
            self::STORE . 'test_api_key' => self::KEY,
            self::STORE . 'test_signing_secret' => self::SECRET,
        ];
    }

    protected static function paymentFields(string $status, array $overrides = []): array
    {
        return $overrides + [
            'id' => self::ID,
            'orderId' => '000000101',
            'amount' => 1000,
            'currency' => 'EUR',
            'livemode' => false,
            'status' => $status,
            'statusCode' => 'E000',
            'statusMessage' => 'Transaction approved',
        ];
    }

    protected function verifier(): CallbackVerifier
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturnCallback(function ($websiteId) {
            $website = $this->createMock(Website::class);
            $website->method('getStoreIds')->willReturn((int) $websiteId === 1 ? ['1', '2'] : ['3']);
            return $website;
        });

        $orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $orderCollectionFactory->method('create')->willReturnCallback(function () {
            $filters = [];
            $collection = $this->createMock(OrderCollection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function ($field, $condition) use (&$filters, $collection) {
                    $filters[$field] = $condition;
                    return $collection;
                }
            );
            $collection->method('getItems')->willReturnCallback(function () use (&$filters) {
                $items = [];
                foreach ($this->orders as $label => $data) {
                    $storeId = $data['storeId'] ?? 1;
                    if (($data['incrementId'] ?? (string) $label) === $filters['increment_id']
                        && in_array($storeId, $filters['store_id']['in'], true)) {
                        $items[] = $this->order($data);
                    }
                }
                return $items;
            });
            return $collection;
        });

        $apiClient = $this->createMock(MoneiApiClient::class);
        $apiClient->method('createSdkForApiKey')->willReturnCallback(function (string $key) {
            $client = $this->getMockBuilder(MoneiClient::class)->disableOriginalConstructor()->getMock();
            $client->payments = $this->createMock(PaymentsApi::class);
            $client->payments->method('get')->willReturnCallback(function (string $id) use ($key) {
                $this->fetches[] = [$key, $id];
                if ($this->fetchFails) {
                    throw new ApiException('[0] cURL error 28', 0);
                }
                return ObjectSerializer::deserialize(
                    json_decode((string) json_encode($this->remote)),
                    '\Monei\Model\Payment'
                );
            });
            return $client;
        });
        $apiClient->expects($this->never())->method('getMoneiSdk');

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(function () {
            return $this->now;
        });

        return new CallbackVerifier(
            $this->fakeScopeConfig(),
            $storeManager,
            $orderCollectionFactory,
            $apiClient,
            $this->flags,
            $dateTime,
            $this->fetchedPayments
        );
    }

    private function order(array $data): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getData')->with('monei_payment_id')->willReturn($data['paymentId'] ?? null);
        $order->method('getState')->willReturn($data['state'] ?? 'new');
        $order->method('hasInvoices')->willReturn($data['paid'] ?? false);
        $order->method('getStoreId')->willReturn($data['storeId'] ?? 1);
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($data['method'] ?? 'monei_card');
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['monei_payment_id', $data['storedStatusOf'] ?? $data['paymentId'] ?? null],
            ['monei_payment_status', $data['storedStatus'] ?? null],
        ]);
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }

    protected static function sign(string $body, string $element, string $key): string
    {
        return 't=1700000000,' . $element . '=' . hash_hmac('SHA256', '1700000000.' . $body, $key);
    }

    protected static function body(array $fields): string
    {
        return (string) json_encode($fields);
    }
}

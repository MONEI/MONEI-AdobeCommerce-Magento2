<?php declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Cron;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Cron\ProcessPendingOrders;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

/**
 * The hourly sync of authorized MONEI orders. Every test names the bug it catches.
 */
class ProcessPendingOrdersTest extends TestCase
{
    /** @var array<string, mixed> The filters the cron set on the order collection */
    private array $filters = [];

    private function cron(array $orders, PaymentProcessor $processor, LockManagerInterface $lockManager): ProcessPendingOrders
    {
        $collection = $this->createMock(OrderCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function ($field, $condition) use ($collection) {
            $this->filters[$field] = $condition;
            return $collection;
        });
        $collection->method('getSize')->willReturn(count($orders));
        $collection->method('getIterator')->willReturn(new \ArrayIterator($orders));
        $collectionFactory = $this->createMock(OrderCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $moduleConfig = $this->createMock(MoneiPaymentModuleConfigInterface::class);
        $moduleConfig->method('getPreAuthorizedStatus')->willReturnCallback(function (?int $storeId = null) {
            return $storeId === 2 ? 'shop_authorized' : 'monei_authorized';
        });
        $stores = [];
        foreach ([1, 2] as $id) {
            $store = $this->createMock(Store::class);
            $store->method('getId')->willReturn($id);
            $stores[] = $store;
        }
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        return new ProcessPendingOrders(
            $collectionFactory,
            $processor,
            $lockManager,
            $moduleConfig,
            $storeManager,
            $this->createMock(Logger::class)
        );
    }

    private function order(string $incrementId): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getData')->with('monei_payment_id')->willReturn('pay_' . $incrementId);

        return $order;
    }

    /**
     * Bug: the cron holds the order lock while processPaymentById() runs, and processPayment() takes the same lock:
     * with a file or cache lock backend (Adobe Commerce Cloud) the second lock fails, so no order ever syncs. Or it
     * syncs an order that a callback is processing now.
     */
    public function testEachUnlockedOrderIsSyncedWithoutAnOuterLock(): void
    {
        $free = $this->order('000000101');
        $busy = $this->order('000000102');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('isOrderLocked')->willReturnCallback(function (string $incrementId) {
            return $incrementId === '000000102';
        });
        $lockManager->expects($this->never())->method('lockOrder');
        $lockManager->expects($this->never())->method('executeWithOrderLock');
        $processor = $this->createMock(PaymentProcessor::class);
        $processor->expects($this->once())->method('processPaymentById')->with($free, 'pay_000000101');

        $this->cron([$free, $busy], $processor, $lockManager)->execute();
    }

    /**
     * Bug: the cron looks only for the default status monei_authorized, so the authorized orders of a store with
     * its own Authorized Status never sync.
     */
    public function testTheAuthorizedStatusOfEveryStoreIsSynced(): void
    {
        $this->cron([], $this->createMock(PaymentProcessor::class), $this->createMock(LockManagerInterface::class))
            ->execute();

        $this->assertSame(['in' => ['monei_authorized', 'shop_authorized']], $this->filters['status']);
    }
}

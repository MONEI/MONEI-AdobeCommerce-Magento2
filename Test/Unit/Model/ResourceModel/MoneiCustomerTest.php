<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Monei\MoneiPayment\Model\ResourceModel\MoneiCustomer;
use PHPUnit\Framework\TestCase;

class MoneiCustomerTest extends TestCase
{
    /**
     * Contract test 6 (newer ID kept). Bug: a stale-customer retry deletes the newer ID another request
     * stored meanwhile, so the next payment creates a second MONEI customer.
     */
    public function testDeleteRowRemovesOnlyTheGivenMoneiCustomerId(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')->with('monei_customer', [
            'customer_id = ?' => 5,
            'account_id = ?' => 'acc_1',
            'mode = ?' => 'test',
            'monei_customer_id = ?' => 'cus_stale',
        ]);
        $resource = $this->getMockBuilder(MoneiCustomer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('monei_customer');

        $resource->deleteRow(5, 'acc_1', 'test', 'cus_stale');
    }

    /**
     * Contract test 4. Bug: live payments get the test-mode customer ID of the same account.
     */
    public function testGetMoneiCustomerIdFiltersByCustomerAccountAndMode(): void
    {
        $conditions = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, $value) use (&$conditions, $select) {
                $conditions[$condition] = $value;
                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn('cus_live');
        $resource = $this->getMockBuilder(MoneiCustomer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('monei_customer');

        $this->assertSame('cus_live', $resource->getMoneiCustomerId(5, 'acc_1', 'live'));
        $this->assertSame(['customer_id = ?' => 5, 'account_id = ?' => 'acc_1', 'mode = ?' => 'live'], $conditions);
    }
}

<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
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
}

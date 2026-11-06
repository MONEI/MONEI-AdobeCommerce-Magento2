<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Observer\DeleteMoneiCustomer;
use Monei\MoneiPayment\Service\Logger;
use PHPUnit\Framework\TestCase;

class DeleteMoneiCustomerTest extends TestCase
{
    /**
     * Bug: a MONEI failure blocks a customer delete in the admin.
     */
    public function testPassesDeletedCustomerIdAndSwallowsServiceError(): void
    {
        $customerService = $this->createMock(CustomerServiceInterface::class);
        $customerService->expects($this->once())
            ->method('deleteCustomer')
            ->with(42)
            ->willThrowException(new \Error('boom'));

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringStartsWith('[MONEI Customers]'));

        $event = new Event(['customer' => new DataObject(['id' => '42'])]);

        (new DeleteMoneiCustomer($customerService, $logger))->execute(new Observer(['event' => $event]));
    }
}

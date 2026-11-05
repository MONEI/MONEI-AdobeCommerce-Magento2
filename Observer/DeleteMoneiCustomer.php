<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Monei\MoneiPayment\Api\Service\Customer\CustomerServiceInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Deletes the linked MONEI customers after a Magento customer is deleted.
 */
class DeleteMoneiCustomer implements ObserverInterface
{
    /**
     * @var CustomerServiceInterface
     */
    private CustomerServiceInterface $customerService;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param CustomerServiceInterface $customerService
     * @param Logger $logger
     */
    public function __construct(CustomerServiceInterface $customerService, Logger $logger)
    {
        $this->customerService = $customerService;
        $this->logger = $logger;
    }

    /**
     * Delete the MONEI customers of the deleted Magento customer.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $customerId = (int) $observer->getEvent()->getCustomer()->getId();

        // A throw here reaches the customer delete in the admin.
        try {
            $this->customerService->deleteCustomer($customerId);
        } catch (\Throwable $e) {
            $this->logger->error('[MONEI Customers] Customer delete failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

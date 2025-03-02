<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\OrderLockManagerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service for processing orders with proper transaction and locking.
 */
class OrderProcessor
{
    /** @var OrderLockManagerInterface */
    private $orderLockManager;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var TransactionFactory */
    private $transactionFactory;

    /** @var Logger */
    private $logger;

    public function __construct(
        OrderLockManagerInterface $orderLockManager,
        OrderRepositoryInterface $orderRepository,
        TransactionFactory $transactionFactory,
        Logger $logger
    ) {
        $this->orderLockManager = $orderLockManager;
        $this->orderRepository = $orderRepository;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute a callback function with a lock to prevent race conditions
     * This method ensures the lock is always released, even if an exception occurs.
     *
     * @param OrderInterface $order
     * @param callable $callback Function to execute while holding the lock
     *
     * @throws LocalizedException
     *
     * @return mixed The result of the callback function, or false if lock couldn't be acquired
     */
    public function executeWithLock(OrderInterface $order, callable $callback)
    {
        $incrementId = $order->getIncrementId();

        if (!$incrementId) {
            throw new LocalizedException(__('Cannot process order without increment ID'));
        }

        // Check if order is already locked
        if ($this->orderLockManager->isLocked($incrementId)) {
            $this->logger->info(\sprintf(
                'Order %s is already being processed by another request',
                $incrementId
            ));

            return false;
        }

        // Acquire lock
        $lockAcquired = $this->orderLockManager->lock($incrementId);
        if (!$lockAcquired) {
            $this->logger->info(\sprintf(
                'Could not acquire lock for order %s',
                $incrementId
            ));

            return false;
        }

        try {
            // Create a database transaction for atomicity
            $transaction = $this->transactionFactory->create();

            // Call the callback
            $result = $callback($order, $transaction);

            // Save the order within the transaction
            if (false !== $result) {
                // Add order to transaction and save
                $transaction->addObject($order);
                $transaction->save();
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Error processing order %s: %s',
                $incrementId,
                $e->getMessage()
            ));

            throw $e;
        } finally {
            // Always release the lock, even if an exception occurs
            $this->orderLockManager->unlock($incrementId);
        }
    }

    /**
     * Load the latest order data and execute a callback with locking.
     *
     * @param string   $incrementId Order increment ID
     * @param callable $callback    Function to execute while holding the lock
     *
     * @throws LocalizedException
     *
     * @return mixed The result of the callback function, or false if lock couldn't be acquired
     */
    public function processOrderById(string $incrementId, callable $callback)
    {
        // Check if order is already locked
        if ($this->orderLockManager->isLocked($incrementId)) {
            $this->logger->info(\sprintf(
                'Order %s is already being processed by another request',
                $incrementId
            ));

            return false;
        }

        // Acquire lock before loading the order
        $lockAcquired = $this->orderLockManager->lock($incrementId);
        if (!$lockAcquired) {
            $this->logger->info(\sprintf(
                'Could not acquire lock for order %s',
                $incrementId
            ));

            return false;
        }

        try {
            // Load the latest order data
            $order = $this->orderRepository->get($incrementId);

            // Create a database transaction for atomicity
            $transaction = $this->transactionFactory->create();

            // Call the callback
            $result = $callback($order, $transaction);

            // Save the order within the transaction
            if (false !== $result) {
                // Add order to transaction and save
                $transaction->addObject($order);
                $transaction->save();
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Error processing order %s: %s',
                $incrementId,
                $e->getMessage()
            ));

            throw $e;
        } finally {
            // Always release the lock, even if an exception occurs
            $this->orderLockManager->unlock($incrementId);
        }
    }
}

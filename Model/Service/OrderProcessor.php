<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Magento\Framework\DB\Transaction;
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
    /**
     * @var OrderLockManagerInterface
     */
    private $orderLockManager;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param OrderLockManagerInterface $orderLockManager
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionFactory $transactionFactory
     * @param Logger $logger
     */
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
        $this->logger->debug('[OrderProcessor initialized]');
    }

    /**
     * Execute a callback function with a lock to prevent race conditions.
     *
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
            $this->logger->error('[Error] Cannot process order without increment ID');

            throw new LocalizedException(__('Cannot process order without increment ID'));
        }

        $this->logger->debug(sprintf('[Attempting to process] Order %s with lock', $incrementId));

        // Check if order is already locked
        if ($this->orderLockManager->isLocked($incrementId)) {
            $this->logger->logOrder($incrementId, '[Order is already being processed] By another request');

            return false;
        }

        // Acquire lock
        $lockAcquired = $this->orderLockManager->lock($incrementId);
        if (!$lockAcquired) {
            $this->logger->logOrder($incrementId, '[Could not acquire lock] For order');

            return false;
        }

        $this->logger->debug(sprintf('[Lock acquired] Order %s', $incrementId));

        try {
            // Create a database transaction for atomicity
            $transaction = $this->transactionFactory->create();
            $this->logger->debug(sprintf('[Transaction created] Order %s', $incrementId));

            // Call the callback
            $this->logger->debug(sprintf('[Executing callback] Order %s', $incrementId));
            $result = $callback($order, $transaction);

            // Save the order within the transaction
            if (false !== $result) {
                $this->logger->debug(sprintf('[Saving order in transaction] Order %s', $incrementId));
                // Add order to transaction and save
                $transaction->addObject($order);
                $transaction->save();
                $this->logger->debug(sprintf('[Order saved successfully] Order %s', $incrementId));
            } else {
                $this->logger->debug(sprintf('[Callback returned false] Order %s, not saving', $incrementId));
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->logOrderError(
                $incrementId,
                sprintf('[Error processing order] %s', $e->getMessage()),
                ['trace' => $e->getTraceAsString()]
            );

            throw $e;
        } finally {
            // Always release the lock, even if an exception occurs
            $this->orderLockManager->unlock($incrementId);
            $this->logger->debug(sprintf('[Lock released] Order %s', $incrementId));
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
        $this->logger->debug(sprintf('[Processing order by ID] %s', $incrementId));

        // Check if order is already locked
        if ($this->orderLockManager->isLocked($incrementId)) {
            $this->logger->logOrder($incrementId, '[Order is already being processed] By another request');

            return false;
        }

        // Acquire lock before loading the order
        $lockAcquired = $this->orderLockManager->lock($incrementId);
        if (!$lockAcquired) {
            $this->logger->logOrder($incrementId, '[Could not acquire lock] For order');

            return false;
        }

        $this->logger->debug(sprintf('[Lock acquired] Order ID %s', $incrementId));

        try {
            // Load the latest order data
            $this->logger->debug(sprintf('[Loading order data] Order %s', $incrementId));
            $order = $this->orderRepository->get($incrementId);
            $this->logger->debug(sprintf('[Order loaded successfully] Order %s', $incrementId));

            // Create a database transaction for atomicity
            $transaction = $this->transactionFactory->create();
            $this->logger->debug(sprintf('[Transaction created] Order %s', $incrementId));

            // Call the callback
            $this->logger->debug(sprintf('[Executing callback] Order %s', $incrementId));
            $result = $callback($order, $transaction);

            // Save the order within the transaction
            if (false !== $result) {
                $this->logger->debug(sprintf('[Saving order in transaction] Order %s', $incrementId));
                // Add order to transaction and save
                $transaction->addObject($order);
                $transaction->save();
                $this->logger->debug(sprintf('[Order saved successfully] Order %s', $incrementId));
            } else {
                $this->logger->debug(sprintf('[Callback returned false] Order %s, not saving', $incrementId));
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->logOrderError(
                $incrementId,
                sprintf('[Error processing order] %s', $e->getMessage()),
                ['trace' => $e->getTraceAsString()]
            );

            throw $e;
        } finally {
            // Always release the lock, even if an exception occurs
            $this->orderLockManager->unlock($incrementId);
            $this->logger->debug(sprintf('[Lock released] Order ID %s', $incrementId));
        }
    }
}

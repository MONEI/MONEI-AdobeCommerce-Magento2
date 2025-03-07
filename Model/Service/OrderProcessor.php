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
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service for processing orders with proper transaction and locking.
 */
class OrderProcessor
{
    /**
     * @var LockManagerInterface
     */
    private $lockManager;

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
     * @param LockManagerInterface $lockManager
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionFactory $transactionFactory
     * @param Logger $logger
     */
    public function __construct(
        LockManagerInterface $lockManager,
        OrderRepositoryInterface $orderRepository,
        TransactionFactory $transactionFactory,
        Logger $logger
    ) {
        $this->lockManager = $lockManager;
        $this->orderRepository = $orderRepository;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->logger->debug('[OrderProcessor initialized]');
    }

    /**
     * Execute a callback with order locking to prevent race conditions.
     *
     * @param OrderInterface $order
     * @param callable $callback
     * @return mixed
     * @throws LocalizedException
     */
    public function executeWithLock(OrderInterface $order, callable $callback)
    {
        $incrementId = $order->getIncrementId();

        if (!$incrementId) {
            $this->logger->error('[Error] Cannot process order without increment ID');

            throw new LocalizedException(__('Cannot process order without increment ID'));
        }

        $this->logger->debug(sprintf('[Attempting to process] Order %s with lock', $incrementId));

        // Try to acquire a lock
        if (!$this->lockManager->lockOrder($incrementId)) {
            $this->logger->warning(sprintf('[OrderProcessor] Could not acquire lock for order %s', $incrementId));

            throw new LocalizedException(__('Order %1 is currently being processed. Please try again later.', $incrementId));
        }

        $this->logger->debug(sprintf('[OrderProcessor] Lock acquired for order %s', $incrementId));

        try {
            // Create a transaction
            /** @var Transaction $transaction */
            $transaction = $this->transactionFactory->create();

            // Execute the callback
            $this->logger->debug(sprintf('[Executing callback] Order %s', $incrementId));
            $result = $callback($order, $transaction);

            // Save the order
            $transaction->addObject($order);
            $transaction->save();

            $this->logger->debug(sprintf('[OrderProcessor] Transaction completed for order %s', $incrementId));

            return $result;
        } catch (\Exception $e) {
            $this->logger->logOrderError(
                $incrementId,
                sprintf('[Error processing order] %s', $e->getMessage()),
                ['trace' => $e->getTraceAsString()]
            );

            throw $e;
        } finally {
            // Always release the lock
            $this->lockManager->unlockOrder($incrementId);
            $this->logger->debug(sprintf('[OrderProcessor] Lock released for order %s', $incrementId));
        }
    }

    /**
     * Process an order by ID with locking.
     *
     * @param string $incrementId
     * @param callable $callback
     * @return mixed
     * @throws LocalizedException
     */
    public function processOrderById(string $incrementId, callable $callback)
    {
        $this->logger->debug(sprintf('[Processing order by ID] %s', $incrementId));

        // Try to acquire a lock
        if (!$this->lockManager->lockOrder($incrementId)) {
            $this->logger->warning(sprintf('[OrderProcessor] Could not acquire lock for order %s', $incrementId));

            throw new LocalizedException(__('Order %1 is currently being processed. Please try again later.', $incrementId));
        }

        $this->logger->debug(sprintf('[OrderProcessor] Lock acquired for order %s', $incrementId));

        try {
            // Load the order
            $this->logger->debug(sprintf('[Loading order data] Order %s', $incrementId));
            $order = $this->orderRepository->get($incrementId);
            $this->logger->debug(sprintf('[Order loaded successfully] Order %s', $incrementId));

            // Create a transaction
            /** @var Transaction $transaction */
            $transaction = $this->transactionFactory->create();

            // Execute the callback
            $this->logger->debug(sprintf('[Executing callback] Order %s', $incrementId));
            $result = $callback($order, $transaction);

            // Save the order
            $transaction->addObject($order);
            $transaction->save();

            $this->logger->debug(sprintf('[OrderProcessor] Transaction completed for order %s', $incrementId));

            return $result;
        } catch (\Exception $e) {
            $this->logger->logOrderError(
                $incrementId,
                sprintf('[Error processing order] %s', $e->getMessage()),
                ['trace' => $e->getTraceAsString()]
            );

            throw $e;
        } finally {
            // Always release the lock
            $this->lockManager->unlockOrder($incrementId);
            $this->logger->debug(sprintf('[OrderProcessor] Lock released for order %s', $incrementId));
        }
    }
}

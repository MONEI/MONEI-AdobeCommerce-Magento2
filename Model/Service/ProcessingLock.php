<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Flag\FlagResource;
use Magento\Framework\FlagFactory;
use Magento\Framework\Lock\LockManagerInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Service for managing processing locks to prevent race conditions
 */
class ProcessingLock
{
    // Lock prefix for Monei payment processing
    private const LOCK_PREFIX = 'monei_payment_processing_';
    
    // Lock timeout in seconds (5 minutes)
    private const LOCK_TIMEOUT = 300;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;
    
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param LockManagerInterface $lockManager
     * @param Logger $logger
     */
    public function __construct(
        LockManagerInterface $lockManager,
        Logger $logger
    ) {
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    /**
     * Acquire a lock for processing an order payment
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId Monei payment ID
     * @return bool True if lock acquired, false otherwise
     */
    public function acquireLock(string $orderId, string $paymentId): bool
    {
        $lockName = $this->getLockName($orderId, $paymentId);
        
        try {
            $locked = $this->lockManager->lock($lockName, self::LOCK_TIMEOUT);
            
            if (!$locked) {
                $this->logger->info(sprintf(
                    'Could not acquire lock for order %s and payment %s - already being processed.',
                    $orderId,
                    $paymentId
                ));
            }
            
            return $locked;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error acquiring lock for order %s: %s',
                $orderId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Release a lock for processing an order payment
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId Monei payment ID
     * @return bool True if lock released, false otherwise
     */
    public function releaseLock(string $orderId, string $paymentId): bool
    {
        $lockName = $this->getLockName($orderId, $paymentId);
        
        try {
            return $this->lockManager->unlock($lockName);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error releasing lock for order %s: %s',
                $orderId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Generate a unique lock name for a given order and payment ID
     *
     * @param string $orderId Order increment ID
     * @param string $paymentId Monei payment ID
     * @return string
     */
    private function getLockName(string $orderId, string $paymentId): string
    {
        return self::LOCK_PREFIX . $orderId . '_' . $paymentId;
    }
}

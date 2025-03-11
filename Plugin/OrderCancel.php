<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Service\Logger;

/**
 * Plugin for handling order cancellation notifications
 */
class OrderCancel
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * After cancel, ensure notification flag is set for the cancellation comment
     *
     * @param Order $subject
     * @param Order $result
     * @return Order
     */
    public function afterCancel(Order $subject, Order $result): Order
    {
        try {
            // Get all status history entries
            $historyEntries = $result->getStatusHistories();
            if (!$historyEntries) {
                return $result;
            }

            // Sort by created_at in descending order to get the most recent entries first
            usort($historyEntries, function ($a, $b) {
                $timeA = $a->getCreatedAt() ? strtotime($a->getCreatedAt()) : 0;
                $timeB = $b->getCreatedAt() ? strtotime($b->getCreatedAt()) : 0;
                return $timeB - $timeA;
            });

            // Find and update the most recent cancellation status entry
            foreach ($historyEntries as $history) {
                if (
                    !$history->getIsCustomerNotified() &&
                    $history->getStatus() === $result->getStatus() &&
                    strpos($history->getComment() ?? '', 'canceled') !== false
                ) {
                    // Mark as notified
                    $history->setIsCustomerNotified(true);
                    break;
                }
            }

            // Save the order to persist the changes
            $result->save();
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error marking history as notified after cancel] %s',
                $e->getMessage()
            ), ['exception' => $e]);
        }

        return $result;
    }
}

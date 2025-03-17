<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
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
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @param Logger $logger
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Logger $logger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
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
            // Skip non-Monei payments
            if (
                !$result->getPayment() ||
                !in_array($result->getPayment()->getMethod(), Monei::PAYMENT_METHODS_MONEI)
            ) {
                return $result;
            }

            $this->logger->debug(sprintf(
                '[Checking canceled order history] Order %s, Status: %s',
                $result->getIncrementId(),
                $result->getStatus()
            ));

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

            $updated = false;

            // Find and update the most recent cancellation status entries
            foreach ($historyEntries as $history) {
                // Check for any entries that match the current status or have cancellation keywords
                if (
                    !$history->getIsCustomerNotified() && (
                        $history->getStatus() === $result->getStatus() ||
                        ($history->getComment() && (
                            stripos((string) $history->getComment(), 'cancel') !== false ||
                            stripos((string) $history->getComment(), 'cancelled') !== false ||
                            stripos((string) $history->getComment(), 'canceled') !== false
                        ))
                    )
                ) {
                    // Mark as notified
                    $history->setIsCustomerNotified(true);
                    $updated = true;

                    $this->logger->debug(sprintf(
                        '[Marked cancellation history as notified] Order %s, Status: %s, Comment: %s',
                        $result->getIncrementId(),
                        $history->getStatus(),
                        (string) ($history->getComment() ?? 'No comment')
                    ));
                }
            }

            // Save the order to persist the changes only if we updated something
            if ($updated) {
                $this->orderRepository->save($result);
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error marking history as notified after cancel] %s',
                $e->getMessage()
            ), ['exception' => $e]);
        }

        return $result;
    }
}

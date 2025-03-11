<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin\Sales\Model\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;

/**
 * Plugin to mark customer as notified when an order is canceled for Monei payments
 */
class Cancel
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param Logger $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Logger $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Mark customer as notified after order cancellation for Monei payments
     *
     * @param Order $subject
     * @param Order $result
     * @return Order
     */
    public function afterCancel(
        Order $subject,
        Order $result
    ): Order {
        try {
            // Only process Monei payments
            if (!in_array($subject->getPayment()->getMethod(), Monei::PAYMENT_METHODS_MONEI)) {
                return $result;
            }

            // Find and update the most recent status history entry for the current status
            $historyEntries = $result->getStatusHistories();
            if (!$historyEntries) {
                $historyEntries = [];
            }

            // Sort by created_at in descending order to get the most recent entries first
            usort($historyEntries, function($a, $b) {
                $timeA = $a->getCreatedAt() ? strtotime($a->getCreatedAt()) : 0;
                $timeB = $b->getCreatedAt() ? strtotime($b->getCreatedAt()) : 0;
                return $timeB - $timeA;
            });

            $currentStatus = $result->getStatus();

            // Find and update status entries related to the cancellation
            foreach ($historyEntries as $history) {
                if ($history->getStatus() == $currentStatus && !$history->getIsCustomerNotified()) {
                    $history->setIsCustomerNotified(true);
                    break;
                }
            }

            $this->orderRepository->save($result);

            $this->logger->info(
                sprintf(
                    '[Order cancellation notification sent] Order %s',
                    $result->getIncrementId()
                )
            );
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    '[Error sending order cancellation notification] %s',
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }

        return $result;
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;

/**
 * Plugin to mark order history entries as notified after invoice email is sent
 */
class OrderInvoiceEmailSent
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
     * After invoice email is sent, mark relevant order history entries as notified
     *
     * @param InvoiceSender $subject
     * @param bool $result
     * @param InvoiceInterface $invoice
     * @return bool
     */
    public function afterSend(
        InvoiceSender $subject,
        bool $result,
        InvoiceInterface $invoice
    ): bool {
        try {
            // Get the order from the invoice's order ID
            $orderId = $invoice->getOrderId();
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);

            // Skip if not a Monei payment
            if (!$order->getPayment() || !in_array($order->getPayment()->getMethod(), Monei::PAYMENT_METHODS_MONEI)) {
                return $result;
            }

            // Only proceed if the email was actually sent
            if (!$result) {
                $this->logger->debug(sprintf(
                    '[Invoice email not sent] Order %s, Invoice %s - Skipping history update',
                    $order->getIncrementId(),
                    $invoice->getIncrementId()
                ));

                return $result;
            }

            $this->logger->debug(sprintf(
                '[Invoice email sent] Order %s, Invoice %s - Updating history entries',
                $order->getIncrementId(),
                $invoice->getIncrementId()
            ));

            // Get all status history entries
            $historyEntries = $order->getStatusHistories();
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

            // Find and update status entries specifically related to the invoice capture
            foreach ($historyEntries as $history) {
                if (
                    !$history->getIsCustomerNotified() && (
                        // Check for capture-related comments
                        ($history->getComment() && strpos((string) $history->getComment(), 'Captured amount') !== false) ||
                        // Check for status-related entries
                        $history->getStatus() == $order->getStatus() ||
                        // Check for capture-related status changes
                        ($history->getEntityName() == 'invoice' && $history->getStatus() == $order->getStatus())
                    )
                ) {
                    // Mark as notified
                    $history->setIsCustomerNotified(true);
                    $updated = true;
                    $this->logger->debug(sprintf(
                        '[Marked history as notified] Order %s, Status: %s, Comment: %s',
                        $order->getIncrementId(),
                        $history->getStatus(),
                        (string) ($history->getComment() ?? 'No comment')
                    ));
                }
            }

            // Save the order to persist the changes only if we updated something
            if ($updated) {
                $order->save();
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error marking history as notified after email] %s',
                $e->getMessage()
            ), ['exception' => $e]);
        }

        return $result;
    }
}

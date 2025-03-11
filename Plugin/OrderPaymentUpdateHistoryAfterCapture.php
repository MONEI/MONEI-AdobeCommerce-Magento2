<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Payment;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;

/**
 * Plugin to mark order history entries as notified after payment capture
 */
class OrderPaymentUpdateHistoryAfterCapture
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
     * After payment capture, mark history entries as notified if invoice email is sent
     *
     * @param Payment $subject
     * @param Payment $result
     * @param InvoiceInterface|null $invoice
     * @return Payment
     */
    public function afterCapture(
        Payment $subject,
        Payment $result,
        ?InvoiceInterface $invoice = null
    ): Payment {
        try {
            // Skip if no invoice or not a Monei payment
            if (!$invoice || !in_array($subject->getMethod(), Monei::PAYMENT_METHODS_MONEI)) {
                return $result;
            }

            // If the invoice email was sent, mark history entries as notified
            if ($invoice->getEmailSent()) {
                $order = $subject->getOrder();

                // Log for debugging
                $this->logger->debug(sprintf(
                    '[Checking history entries] Order %s, Invoice %s, Email Sent: true',
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

                // Find and update status entries specifically related to this capture
                foreach ($historyEntries as $history) {
                    if (
                        !$history->getIsCustomerNotified() &&
                        $history->getComment() &&
                        (
                            strpos($history->getComment(), 'Captured amount') !== false ||
                            $history->getStatus() == $order->getStatus()
                        )
                    ) {
                        // Mark as notified
                        $history->setIsCustomerNotified(true);
                        $this->logger->debug(sprintf(
                            '[Marked history as notified] Order %s, Comment: %s',
                            $order->getIncrementId(),
                            $history->getComment()
                        ));
                        break;
                    }
                }

                // Save the order to persist the changes
                $order->save();
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error marking history as notified after capture] %s',
                $e->getMessage()
            ), ['exception' => $e]);
        }

        return $result;
    }
}

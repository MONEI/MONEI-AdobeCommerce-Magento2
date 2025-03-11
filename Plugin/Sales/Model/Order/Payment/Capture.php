<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin\Sales\Model\Order\Payment;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;

/**
 * Plugin to send invoice email after capture for Monei payments
 */
class Capture
{
    /**
     * @var InvoiceSender
     */
    private InvoiceSender $invoiceSender;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param InvoiceSender $invoiceSender
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     */
    public function __construct(
        InvoiceSender $invoiceSender,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger
    ) {
        $this->invoiceSender = $invoiceSender;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
    }

    /**
     * Send invoice email after capture for Monei payments
     *
     * @param Payment $subject
     * @param Payment $result
     * @param InvoiceInterface $invoice
     * @return Payment
     */
    public function afterCapture(
        Payment $subject,
        Payment $result,
        InvoiceInterface $invoice
    ): Payment {
        try {
            // Only process Monei payments
            if (!in_array($subject->getMethod(), Monei::PAYMENT_METHODS_MONEI)) {
                return $result;
            }

            // Check if invoice emails should be sent based on configuration
            if (!$this->moduleConfig->shouldSendInvoiceEmail($subject->getOrder()->getStoreId())) {
                return $result;
            }

            // Skip if the invoice email has already been sent
            if ($invoice->getEmailSent()) {
                return $result;
            }

            // Check if this was a previously authorized payment
            // For manually created invoices, the merchant can choose to send the email during invoice creation
            $wasAuthorized = $subject->getAuthorizationTransaction() &&
                             $subject->getAuthorizationTransaction()->getCreatedAt() != $subject->getCreatedAt();

            // Only automatically send invoice emails for automatically created invoices (not manual ones)
            if (!$wasAuthorized) {
                // Send the invoice email for automatically created invoices
                $this->logger->debug('[Sending invoice email after automatic capture]');
                $this->invoiceSender->send($invoice);

                // Mark the invoice as having its email sent
                if (!$invoice->getEmailSent()) {
                    $invoice->setEmailSent(true);
                }
            }

            // Mark the customer as notified about the capture in the order status history
            // regardless of whether an email was sent (for the order timeline)
            $order = $subject->getOrder();

            // Get all status history entries
            $historyEntries = $order->getStatusHistories();
            if (!$historyEntries) {
                $historyEntries = [];
            }

            // Sort by created_at in descending order to get the most recent entries first
            usort($historyEntries, function($a, $b) {
                $timeA = $a->getCreatedAt() ? strtotime($a->getCreatedAt()) : 0;
                $timeB = $b->getCreatedAt() ? strtotime($b->getCreatedAt()) : 0;
                return $timeB - $timeA;
            });

            // Find and update status entries specifically related to this capture/invoice
            foreach ($historyEntries as $history) {
                if (!$history->getIsCustomerNotified() &&
                    (strpos($history->getComment() ?? '', 'Captured amount') !== false ||
                     $history->getStatus() == $order->getStatus())) {
                    // For authorized payments where invoice is created manually, we still mark the status history
                    // as notified if the merchant chose to send a notification during invoice creation
                    if ($wasAuthorized && $invoice->getEmailSent()) {
                        $history->setIsCustomerNotified(true);
                    }
                    // For automatic captures, we always mark it as notified based on our email sending
                    else if (!$wasAuthorized) {
                        $history->setIsCustomerNotified(true);
                    }
                    break;
                }
            }

            $order->save();

            $this->logger->info(
                sprintf(
                    '[Invoice email status] Order %s, Invoice %s, Email Sent: %s',
                    $subject->getOrder()->getIncrementId(),
                    $invoice->getIncrementId(),
                    $invoice->getEmailSent() ? 'Yes' : 'No'
                )
            );
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    '[Error processing invoice notification] %s',
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }

        return $result;
    }
}

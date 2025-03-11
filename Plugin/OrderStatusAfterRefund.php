<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Monei\MoneiPayment\Model\Config\MoneiPaymentModuleConfig;
use Monei\MoneiPayment\Service\Logger;

/**
 * Plugin for setting order status after refund
 */
class OrderStatusAfterRefund
{
    /**
     * @var MoneiPaymentModuleConfig
     */
    private MoneiPaymentModuleConfig $config;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private OrderStatusHistoryRepositoryInterface $historyRepository;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param MoneiPaymentModuleConfig $config
     * @param OrderStatusHistoryRepositoryInterface $historyRepository
     * @param Logger $logger
     */
    public function __construct(
        MoneiPaymentModuleConfig $config,
        OrderStatusHistoryRepositoryInterface $historyRepository,
        Logger $logger
    ) {
        $this->config = $config;
        $this->historyRepository = $historyRepository;
        $this->logger = $logger;
    }

    /**
     * After save credit memo, update order status if needed
     *
     * @param CreditmemoRepositoryInterface $subject
     * @param Creditmemo $creditmemo
     * @return Creditmemo
     */
    public function afterSave(
        CreditmemoRepositoryInterface $subject,
        Creditmemo $creditmemo
    ): Creditmemo {
        try {
            $refundedStatus = $this->config->getRefundedOrderStatus();
            // Skip if refunded status is not set in configuration
            if (!$refundedStatus) {
                return $creditmemo;
            }

            /** @var Order $order */
            $order = $creditmemo->getOrder();
            // Skip for closed orders or orders with no refunded status
            if ($order->getState() === Order::STATE_CLOSED) {
                return $creditmemo;
            }

            $totalRefunded = $order->getTotalRefunded() ?: 0;
            $totalPaid = $order->getTotalPaid() ?: 0;

            // Set refunded status only if all was refunded
            if ($totalRefunded > 0 && $totalRefunded >= $totalPaid) {
                $order->setStatus($refundedStatus);
                $comment = __('Order status set to %1 after full refund.', $refundedStatus);

                // Add a status history comment
                $history = $order->addStatusHistoryComment($comment, $refundedStatus);
                $history->setIsCustomerNotified(true);
                $this->historyRepository->save($history);

                // Save the order
                $order->save();

                $this->logger->debug(sprintf(
                    'Order %s status updated to %s after full refund',
                    $order->getIncrementId(),
                    $refundedStatus
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Error setting order status after refund] %s',
                $e->getMessage()
            ), ['exception' => $e]);
        }

        return $creditmemo;
    }
}

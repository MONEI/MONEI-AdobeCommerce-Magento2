<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;
use Psr\Log\LoggerInterface;

/**
 * Set status and state for Monei order after credit memo creation.
 */
class SetOrderStatusAfterRefund
{
    /**
     * Order repository for loading and saving orders.
     *
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Logger for error reporting.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param OrderRepositoryInterface $orderRepository Order repository for loading and saving orders
     * @param LoggerInterface $logger Logger for error reporting
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Sets status and state for order after refund.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param CreditmemoService $subject Credit memo service
     * @param CreditmemoInterface $result The created credit memo
     *
     * @return CreditmemoInterface The credit memo instance
     */
    public function afterRefund(CreditmemoService $subject, CreditmemoInterface $result): CreditmemoInterface
    {
        try {
            // Access order using the orderId property
            $orderId = $result->getOrderId();
            if (!$orderId) {
                return $result;
            }

            $order = $this->orderRepository->get($orderId);

            // Check if this is a Monei payment and not already closed
            if (null !== $order->getData('monei_payment_id') &&
                'closed' !== $order->getState()
            ) {
                $orderStatus = Monei::STATUS_MONEI_PARTIALLY_REFUNDED;
                $orderState = Order::STATE_PROCESSING;
                $order->setStatus($orderStatus)->setState($orderState);
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            // Silently handle any exceptions to avoid breaking the checkout flow
            // Log the exception for debugging purposes
            $this->logger->error(
                '[Order status update] Error updating after refund: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $result;
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\CreditmemoService;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Set status and state for Monei order after credit memo creation.
 */
class SetOrderStatusAfterRefund
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Sets status and state for order.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param CreditmemoService $subject
     * @param CreditmemoInterface $result
     */
    public function afterRefund(CreditmemoService $subject, CreditmemoInterface $result): CreditmemoInterface
    {
        $order = $result->getOrder();
        if (null !== $order->getData('monei_payment_id')
            && null !== $result->getRefundReason()
            && 'closed' !== $order->getState()
        ) {
            $orderStatus = Monei::STATUS_MONEI_PARTIALLY_REFUNDED;
            $orderState = Order::STATE_PROCESSING;
            $order->setStatus($orderStatus)->setState($orderState);
            $this->orderRepository->save($order);
        }

        return $result;
    }
}

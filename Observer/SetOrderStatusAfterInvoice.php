<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Monei\MoneiPayment\Model\Payment\Monei;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Set status and state for Monei order after invoice generation.
 */
class SetOrderStatusAfterInvoice implements ObserverInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getOrder();
        $invoice = $observer->getInvoice();
        if ($order->getData('monei_payment_id') !== null && $invoice->getIsPaid() === true) {
            $orderStatus = Monei::STATUS_MONEI_SUCCEDED;
            $orderState = Order::STATE_PROCESSING;
            $order->setStatus($orderStatus)->setState($orderState);
            $this->orderRepository->save($order);
        }
    }
}

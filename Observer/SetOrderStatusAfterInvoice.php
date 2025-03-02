<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Set status and state for Monei order after invoice generation.
 */
class SetOrderStatusAfterInvoice implements ObserverInterface
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var MoneiPaymentModuleConfigInterface */
    private $moduleConfig;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->moduleConfig = $moduleConfig;
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getOrder();
        $invoice = $observer->getInvoice();
        if (null !== $order->getData('monei_payment_id') && true === $invoice->getIsPaid()) {
            $orderStatus = $this->moduleConfig->getConfirmedStatus($order->getStoreId())
                ?? Monei::STATUS_MONEI_SUCCEDED;
            $orderState = Order::STATE_PROCESSING;
            $order->setStatus($orderStatus)->setState($orderState);
            $this->orderRepository->save($order);
        }
    }
}

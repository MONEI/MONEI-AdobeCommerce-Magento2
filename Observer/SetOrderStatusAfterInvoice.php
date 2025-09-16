<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * Constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Execute observer to set order status after invoice generation.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getOrder();
        $invoice = $observer->getInvoice();

        // Only proceed if this is a Monei payment and the invoice is paid
        if (null !== $order->getData('monei_payment_id') && true === $invoice->getIsPaid()) {
            $confirmedStatus = $this->moduleConfig->getConfirmedStatus((int) $order->getStoreId())
                ?? Monei::STATUS_MONEI_SUCCEEDED;

            // Only update if the order is not already in the confirmed status
            // This prevents redundancy with the refactored payment processing logic
            if ($order->getStatus() !== $confirmedStatus) {
                $orderState = Order::STATE_PROCESSING;
                $order->setStatus($confirmedStatus)->setState($orderState);
                $this->orderRepository->save($order);
            }
        }
    }
}

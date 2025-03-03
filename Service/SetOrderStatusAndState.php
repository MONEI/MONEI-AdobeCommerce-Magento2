<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Set order status and state depending from response data service class.
 */
class SetOrderStatusAndState implements SetOrderStatusAndStateInterface
{
    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @param \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory Class from Magento\Sales\Api\Data namespace
     * @param OrderRepositoryInterface $orderRepository
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     */
    public function __construct(
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        OrderRepositoryInterface $orderRepository,
        MoneiPaymentModuleConfigInterface $moduleConfig
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Set order status and state based on payment data.
     *
     * @param array $data Payment data containing orderId and status
     *
     * @return bool True if the order status was updated successfully
     */
    public function execute(array $data): bool
    {
        /** @var OrderInterface $order */
        $order = $this->orderFactory->create()->loadByIncrementId($data['orderId']);
        $oldOrderStatus = $order->getStatus();
        $status = $data['status'] ?? null;

        switch ($status) {
            case Monei::ORDER_STATUS_PENDING:
                $orderStatus = Monei::STATUS_MONEI_PENDING;
                $orderState = Order::STATE_PENDING_PAYMENT;

                break;

            case Monei::ORDER_STATUS_AUTHORIZED:
                $orderStatus = Monei::STATUS_MONEI_AUTHORIZED;
                $orderState = Order::STATE_PENDING_PAYMENT;

                break;

            case Monei::ORDER_STATUS_EXPIRED:
                $orderStatus = Monei::STATUS_MONEI_EXPIRED;
                $orderState = Order::STATE_CANCELED;

                break;

            case Monei::ORDER_STATUS_CANCELED:
                $orderStatus = Order::STATE_CANCELED;
                $orderState = Order::STATE_CANCELED;

                break;

            case Monei::ORDER_STATUS_FAILED:
                $orderStatus = Monei::STATUS_MONEI_FAILED;
                $orderState = Order::STATE_CANCELED;

                break;

            case Monei::ORDER_STATUS_SUCCEEDED:
                $orderStatus = $this->moduleConfig->getConfirmedStatus($order->getStoreId())
                    ?? Monei::STATUS_MONEI_SUCCEDED;
                $orderState = Order::STATE_PROCESSING;

                break;

            case Monei::ORDER_STATUS_PARTIALLY_REFUNDED:
                $orderStatus = Monei::STATUS_MONEI_PARTIALLY_REFUNDED;
                $orderState = Order::STATE_PROCESSING;

                break;

            case Monei::ORDER_STATUS_REFUNDED:
                $orderStatus = Monei::STATUS_MONEI_REFUNDED;
                $orderState = Order::STATE_COMPLETE;

                break;

            default:
                $orderStatus = $order->getStatus();
                $orderState = $order->getState();

                break;
        }

        if ($orderStatus !== $oldOrderStatus) {
            $order->setStatus($orderStatus)->setState($orderState);
            $this->orderRepository->save($order);

            return true;
        }

        return false;
    }
}

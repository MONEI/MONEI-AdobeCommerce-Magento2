<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Api\Service\SetOrderStatusAndStateInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder\Collection;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder\CollectionFactory;

/**
 * Cron job for processing orders with Monei payment method
 */
class ProcessPendingOrders
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * @var GetPaymentInterface
     */
    private $getPaymentService;

    /**
     * @var GenerateInvoiceInterface
     */
    private $generateInvoiceService;

    /**
     * @var SetOrderStatusAndStateInterface
     */
    private $setOrderStatusAndStateService;

    /**
     * @var PendingOrderResource
     */
    private $pendingOrderResource;

    /**
     * @var DateTime
     */
    private $date;

    /**
     * @var CancelPaymentInterface
     */
    private $cancelPaymentService;

    /**
     * @param CollectionFactory               $collectionFactory
     * @param OrderInterfaceFactory           $orderFactory
     * @param GetPaymentInterface             $getPaymentService
     * @param GenerateInvoiceInterface        $generateInvoiceService
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService
     * @param PendingOrderResource            $pendingOrderResource
     * @param DateTime                        $date
     * @param CancelPaymentInterface          $cancelPaymentService
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        OrderInterfaceFactory $orderFactory,
        GetPaymentInterface $getPaymentService,
        GenerateInvoiceInterface $generateInvoiceService,
        SetOrderStatusAndStateInterface $setOrderStatusAndStateService,
        PendingOrderResource $pendingOrderResource,
        DateTime $date,
        CancelPaymentInterface $cancelPaymentService
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->orderFactory = $orderFactory;
        $this->getPaymentService = $getPaymentService;
        $this->generateInvoiceService = $generateInvoiceService;
        $this->setOrderStatusAndStateService = $setOrderStatusAndStateService;
        $this->pendingOrderResource = $pendingOrderResource;
        $this->date = $date;
        $this->cancelPaymentService = $cancelPaymentService;
    }

    /**
     * Set order status if order is succeeded or canceled in Monei or cancel it if order is 7 days or older.
     *
     * @return void
     */
    public function execute(): void
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create()->load();

        foreach ($collection as $item) {
            $date = $this->date->date();
            $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderIncrementId());
            if ($order->getStatus() !== Monei::STATUS_MONEI_AUTHORIZED) {
                $this->pendingOrderResource->delete($item);
                continue;
            }

            $orderDate = $order->getCreatedAt();
            $diff = (int)(strtotime($date) - strtotime($orderDate)) / (60 * 60 * 24);
            if ($diff >= 7) {
                $data = [
                    'paymentId' => $order->getData('monei_payment_id'),
                    'cancellationReason' => 'fraudulent',
                ];
                $response = $this->cancelPaymentService->execute($data);
                $this->setOrderStatusAndStateService->execute($response);
                $this->pendingOrderResource->delete($item);
                continue;
            }

            $response = $this->getPaymentService->execute($order->getData('monei_payment_id'));
            if ($response['status'] === Monei::ORDER_STATUS_SUCCEEDED) {
                $this->generateInvoiceService->execute($response);
                $this->setOrderStatusAndStateService->execute($response);
                $this->pendingOrderResource->delete($item);
            } elseif ($response['status'] === Monei::ORDER_STATUS_CANCELED) {
                $this->setOrderStatusAndStateService->execute($response);
                $this->pendingOrderResource->delete($item);
            }
        }
    }
}

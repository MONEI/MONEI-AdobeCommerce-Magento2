<?php

/**
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
 * Cron job for processing orders with Monei payment method.
 */
class ProcessPendingOrders
{
    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var OrderInterfaceFactory */
    private $orderFactory;

    /** @var GetPaymentInterface */
    private $getPaymentService;

    /** @var GenerateInvoiceInterface */
    private $generateInvoiceService;

    /** @var SetOrderStatusAndStateInterface */
    private $setOrderStatusAndStateService;

    /** @var PendingOrderResource */
    private $pendingOrderResource;

    /** @var DateTime */
    private $date;

    /** @var CancelPaymentInterface */
    private $cancelPaymentService;

    /**
     * Constructor.
     *
     * @param CollectionFactory $collectionFactory Factory for creating pending order collections
     * @param OrderInterfaceFactory $orderFactory Factory for creating orders
     * @param GetPaymentInterface $getPaymentService Service for retrieving payment details
     * @param GenerateInvoiceInterface $generateInvoiceService Service for generating invoices
     * @param SetOrderStatusAndStateInterface $setOrderStatusAndStateService Service for setting order status
     * @param PendingOrderResource $pendingOrderResource Resource model for pending orders
     * @param DateTime $date Date utility
     * @param CancelPaymentInterface $cancelPaymentService Service for canceling payments
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
     * Execute cron job to process pending orders.
     *
     * Processes orders with Monei payment method that are in a pending state.
     * Checks payment status and updates order accordingly.
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
            if (Monei::STATUS_MONEI_AUTHORIZED !== $order->getStatus()) {
                $this->pendingOrderResource->delete($item);

                continue;
            }

            $orderDate = $order->getCreatedAt();
            $diff = (int) (strtotime($date) - strtotime($orderDate)) / (60 * 60 * 24);
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
            if (Monei::ORDER_STATUS_SUCCEEDED === $response['status']) {
                $this->generateInvoiceService->execute($response);
                $this->setOrderStatusAndStateService->execute($response);
                $this->pendingOrderResource->delete($item);
            } elseif (Monei::ORDER_STATUS_CANCELED === $response['status']) {
                $this->setOrderStatusAndStateService->execute($response);
                $this->pendingOrderResource->delete($item);
            }
        }
    }
}

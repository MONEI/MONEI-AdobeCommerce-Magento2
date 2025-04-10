<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\OrderFactory;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Service\CancelPaymentInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;

/**
 * Cron job for processing orders with Monei payment method.
 */
class ProcessPendingOrders
{
    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var PaymentProcessor
     */
    private $paymentProcessor;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * @var DateTime
     */
    private $date;

    /**
     * @var CancelPaymentInterface
     */
    private $cancelPaymentService;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderFactory $orderFactory
     * @param PaymentProcessor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param LockManagerInterface $lockManager
     * @param DateTime $date
     * @param CancelPaymentInterface $cancelPaymentService
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param Logger $logger
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        OrderFactory $orderFactory,
        PaymentProcessor $paymentProcessor,
        OrderRepositoryInterface $orderRepository,
        LockManagerInterface $lockManager,
        DateTime $date,
        CancelPaymentInterface $cancelPaymentService,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Logger $logger
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->orderRepository = $orderRepository;
        $this->lockManager = $lockManager;
        $this->date = $date;
        $this->cancelPaymentService = $cancelPaymentService;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
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
        $this->logger->info('[Cron] Starting to process pending Monei orders');

        // Get orders with Monei payment method in authorized status
        /** @var OrderCollection $collection */
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('status', Status::getMagentoStatus(Status::AUTHORIZED));
        $collection->addFieldToFilter('monei_payment_id', ['notnull' => true]);

        $this->logger->info(sprintf('[Cron] Found %d pending orders to process', $collection->getSize()));

        foreach ($collection as $order) {
            $incrementId = $order->getIncrementId();
            $paymentId = $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);

            if (!$paymentId) {
                $this->logger->warning(sprintf('[Cron] Order %s has no payment ID', $incrementId));

                continue;
            }

            // Skip if the order is already being processed
            if ($this->lockManager->isOrderLocked($incrementId)) {
                $this->logger->info(sprintf('[Cron] Order %s is locked, skipping', $incrementId));

                continue;
            }

            // Process the order using a lock to prevent concurrent processing
            try {
                $this->lockManager->executeWithOrderLock($incrementId, function () use ($order, $paymentId, $incrementId) {
                    $this->logger->info(sprintf('[Cron] Processing order %s with payment ID %s', $incrementId, $paymentId));

                    $orderDate = $order->getCreatedAt();
                    $currentDate = $this->date->date();
                    $daysDiff = (int) (strtotime($currentDate) - strtotime($orderDate)) / (60 * 60 * 24);

                    // Cancel payments older than 7 days
                    if ($daysDiff >= 7) {
                        $this->logger->info(sprintf('[Cron] Order %s is %d days old, canceling payment', $incrementId, $daysDiff));

                        try {
                            $data = [
                                'paymentId' => $paymentId,
                                'cancellationReason' => 'abandoned',
                            ];
                            $this->cancelPaymentService->execute($data);

                            // Process the canceled payment
                            $this->paymentProcessor->processPaymentById($order, $paymentId);
                        } catch (\Exception $e) {
                            $this->logger->error(sprintf(
                                '[Cron] Error canceling payment for order %s: %s',
                                $incrementId,
                                $e->getMessage()
                            ));
                        }

                        return;
                    }

                    // Process the payment to check its current status
                    try {
                        $this->paymentProcessor->processPaymentById($order, $paymentId);
                    } catch (\Exception $e) {
                        $this->logger->error(sprintf(
                            '[Cron] Error processing payment for order %s: %s',
                            $incrementId,
                            $e->getMessage()
                        ));
                    }
                });
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[Cron] Error acquiring lock for order %s: %s',
                    $incrementId,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info('[Cron] Finished processing pending Monei orders');
    }
}

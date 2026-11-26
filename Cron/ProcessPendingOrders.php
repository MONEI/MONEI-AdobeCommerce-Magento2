<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Cron;

use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Model\PaymentProcessor;
use Monei\MoneiPayment\Service\Logger;

/**
 * Sync authorized MONEI orders with their payment: the safety net for a missed callback.
 *
 * It applies the status MONEI answers (an expired or canceled authorization cancels the order) and never cancels a
 * payment itself: a Bizum authorization stays capturable for 30 days.
 */
class ProcessPendingOrders
{
    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var PaymentProcessor
     */
    private $paymentProcessor;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param PaymentProcessor $paymentProcessor
     * @param LockManagerInterface $lockManager
     * @param MoneiPaymentModuleConfigInterface $moduleConfig
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        PaymentProcessor $paymentProcessor,
        LockManagerInterface $lockManager,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        StoreManagerInterface $storeManager,
        Logger $logger
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->paymentProcessor = $paymentProcessor;
        $this->lockManager = $lockManager;
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Sync the authorized MONEI orders with MONEI
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('[Cron] Starting to process pending Monei orders');

        /** @var OrderCollection $collection */
        $collection = $this->orderCollectionFactory->create();
        $authorizedStatuses = $this->authorizedStatuses();
        $collection->addFieldToFilter('status', ['in' => array_values(array_unique($authorizedStatuses))]);
        $collection->addFieldToFilter(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID, ['notnull' => true]);

        $this->logger->info(sprintf('[Cron] Found %d pending orders to process', $collection->getSize()));

        foreach ($collection as $order) {
            $incrementId = $order->getIncrementId();
            $paymentId = (string) $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
            // The IN filter mixes the stores' statuses: another store's Authorized Status can mean "paid" here.
            $storeStatus = $authorizedStatuses[(int) $order->getStoreId()] ?? $authorizedStatuses[0];
            if ($paymentId === '' || $order->getStatus() !== $storeStatus) {
                continue;
            }
            // A callback or a redirect is processing it now.
            if ($this->lockManager->isOrderLocked($incrementId)) {
                $this->logger->info(sprintf('[Cron] Order %s is locked, skipping', $incrementId));

                continue;
            }

            // processPayment() takes the order lock: a second lock on it fails with a file or cache lock backend.
            try {
                $this->paymentProcessor->processPaymentById($order, $paymentId);
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[Cron] Error processing payment for order %s: %s',
                    $incrementId,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info('[Cron] Finished processing pending Monei orders');
    }

    /**
     * Get the Authorized Status of every store: the status an authorized order gets is a setting per store
     *
     * @return array<int, string> By store ID; 0 is the default scope
     */
    private function authorizedStatuses(): array
    {
        $statuses = [0 => $this->moduleConfig->getPreAuthorizedStatus()];
        foreach ($this->storeManager->getStores() as $store) {
            $statuses[(int) $store->getId()] = $this->moduleConfig->getPreAuthorizedStatus((int) $store->getId());
        }

        return $statuses;
    }
}

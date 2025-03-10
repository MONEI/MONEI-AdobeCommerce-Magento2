<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\OrderLockManagerInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;

/**
 * Generate invoice for order service class.
 */
class GenerateInvoice implements GenerateInvoiceInterface
{
    /**
     * Order factory for creating order instances.
     * @var OrderInterfaceFactory
     */
    private OrderInterfaceFactory $orderFactory;

    /**
     * Transaction factory for managing database transactions.
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * Order lock manager service.
     * @var OrderLockManagerInterface
     */
    private OrderLockManagerInterface $orderLockManager;

    /**
     * Service for creating vault payments.
     * @var CreateVaultPayment
     */
    private CreateVaultPayment $createVaultPayment;

    /**
     * Logger for payment-related operations.
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param OrderInterfaceFactory $orderFactory
     * @param TransactionFactory $transactionFactory
     * @param OrderLockManagerInterface $orderLockManager
     * @param CreateVaultPayment $createVaultPayment
     * @param Logger $logger
     */
    public function __construct(
        OrderInterfaceFactory $orderFactory,
        TransactionFactory $transactionFactory,
        OrderLockManagerInterface $orderLockManager,
        CreateVaultPayment $createVaultPayment,
        Logger $logger
    ) {
        $this->createVaultPayment = $createVaultPayment;
        $this->orderLockManager = $orderLockManager;
        $this->transactionFactory = $transactionFactory;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    /**
     * Generate an invoice for the specified order.
     *
     * Takes payment data including orderId and creates an invoice for that order.
     * Handles payment processing, transaction management, and vault payment creation if needed.
     *
     * @param array $data Payment data containing orderId and other payment details
     * @return void
     */
    public function execute(array $data): void
    {
        $incrementId = $data['orderId'];

        /** @var OrderInterface $order */
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (!$order->getId()) {
            return;
        }

        $isOrderLocked = $this->orderLockManager->isLocked($incrementId);
        if ($isOrderLocked || $this->isOrderAlreadyPaid($order)) {
            return;
        }

        // Get lock before processing
        $lockAcquired = $this->orderLockManager->lock($incrementId);
        if (!$lockAcquired) {
            $this->logger->info(\sprintf(
                'Could not acquire lock for order %s - already being processed',
                $incrementId
            ));

            return;
        }

        try {
            $payment = $order->getPayment();
            if ($payment) {
                $payment->setLastTransId($data['id']);
            }
            $invoice = $order->prepareInvoice();
            if (!$invoice->getAllItems()) {
                return;
            }
            $invoice->register()->capture();
            $order->addRelatedObject($invoice);
            if ($payment) {
                $payment->setCreatedInvoice($invoice);
                if ($order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)) {
                    $vaultCreated = $this->createVaultPayment->execute(
                        $data['id'],
                        $payment
                    );
                    $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $vaultCreated);
                }
            }
            $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder())->save();
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Error generating invoice for order %s: %s',
                $incrementId,
                $e->getMessage()
            ));

            throw $e; // Rethrow the exception after logging
        } finally {
            // Always release the lock, even if an exception occurred
            $this->orderLockManager->unlock($incrementId);
        }
    }

    /**
     * Check if the order is already paid.
     *
     * @param OrderInterface $order Order to check
     *
     * @return bool True if order is already paid, false otherwise
     */
    private function isOrderAlreadyPaid(OrderInterface $order): bool
    {
        $payment = $order->getPayment();

        return $payment && $payment->getLastTransId() && $payment->getAmountPaid() && !$order->getTotalDue();
    }
}

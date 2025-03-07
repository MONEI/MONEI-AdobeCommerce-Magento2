<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory as OrderInterfaceFactory;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\LockManagerInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;

/**
 * Generate invoice for order service class
 */
class GenerateInvoice implements GenerateInvoiceInterface
{
    /**
     * @var OrderInterfaceFactory
     */
    private OrderInterfaceFactory $orderFactory;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * @var LockManagerInterface
     */
    private LockManagerInterface $lockManager;

    /**
     * @var CreateVaultPayment
     */
    private CreateVaultPayment $createVaultPayment;

    /**
     * @param OrderInterfaceFactory $orderFactory
     * @param TransactionFactory $transactionFactory
     * @param LockManagerInterface $lockManager
     * @param CreateVaultPayment $createVaultPayment
     */
    public function __construct(
        OrderInterfaceFactory $orderFactory,
        TransactionFactory $transactionFactory,
        LockManagerInterface $lockManager,
        CreateVaultPayment $createVaultPayment
    ) {
        $this->orderFactory = $orderFactory;
        $this->transactionFactory = $transactionFactory;
        $this->lockManager = $lockManager;
        $this->createVaultPayment = $createVaultPayment;
    }

    /**
     * @inheritdoc
     *
     * @param OrderInterface|string $order Order object or increment ID
     * @param array|null $paymentData Payment data from MONEI
     * @return void
     */
    public function execute($order, $paymentData = null): void
    {
        if (!is_object($order)) {
            $incrementId = $order;
            /** @var OrderInterface $order */
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if (!$order->getId()) {
                return;
            }
        } else {
            $incrementId = $order->getIncrementId();
        }

        $isOrderLocked = $this->lockManager->isOrderLocked($incrementId);
        if ($isOrderLocked || $this->isOrderAlreadyPaid($order)) {
            return;
        }

        $this->lockManager->lockOrder($incrementId);
        try {
            $payment = $order->getPayment();
            if ($payment && $paymentData) {
                $payment->setLastTransId($paymentData['id']);
            }
            $invoice = $order->prepareInvoice();
            if (!$invoice->getAllItems()) {
                return;
            }
            $invoice->register()->capture();
            $order->addRelatedObject($invoice);
            if ($payment) {
                $payment->setCreatedInvoice($invoice);
                if ($order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION) && $paymentData) {
                    $vaultCreated = $this->createVaultPayment->execute(
                        $paymentData['id'],
                        $payment
                    );
                    $order->setData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION, $vaultCreated);
                }
            }
            $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder())->save();
        } finally {
            $this->lockManager->unlockOrder($incrementId);
        }
    }

    /**
     * Check if order is already paid
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isOrderAlreadyPaid(OrderInterface $order): bool
    {
        /** @var Order $order */
        return $order->hasInvoices() && $order->getBaseTotalDue() <= 0;
    }
}

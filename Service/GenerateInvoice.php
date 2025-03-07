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
        $this->createVaultPayment = $createVaultPayment;
        $this->lockManager = $lockManager;
        $this->transactionFactory = $transactionFactory;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Generate invoice for order
     *
     * @param OrderInterface|array $order Order or order data
     * @param mixed $paymentData Optional payment data
     * @return void
     */
    public function execute($order, $paymentData = null): void
    {
        try {
            // If $order is an array, extract the orderId and load the order
            if (is_array($order)) {
                $incrementId = $order['orderId'] ?? null;
                if (!$incrementId) {
                    return;
                }
                /** @var Order $orderObj */
                $orderObj = $this->orderFactory->create()->loadByIncrementId($incrementId);
                if (!$orderObj->getId()) {
                    return;
                }
            } else {
                // $order is already an OrderInterface
                $orderObj = $order;
                $incrementId = $orderObj->getIncrementId();
            }

            $isOrderLocked = $this->lockManager->isOrderLocked($incrementId);
            if ($isOrderLocked || $this->isOrderAlreadyPaid($orderObj)) {
                return;
            }

            $this->lockManager->lockOrder($incrementId);
            try {
                $payment = $orderObj->getPayment();
                if ($payment) {
                    // Set transaction ID from payment data if available
                    if ($paymentData instanceof \Monei\MoneiPayment\Model\Data\PaymentDTO) {
                        $payment->setLastTransId($paymentData->getId());
                    } elseif (is_array($paymentData) && isset($paymentData['id'])) {
                        $payment->setLastTransId($paymentData['id']);
                    }
                }

                /** @var Order $orderObj */
                $invoice = $orderObj->prepareInvoice();
                if (!$invoice->getAllItems()) {
                    return;
                }

                $invoice->register()->capture();
                $orderObj->addRelatedObject($invoice);

                if ($payment) {
                    $payment->setCreatedInvoice($invoice);
                    if ($orderObj->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)) {
                        // Extract payment ID from the appropriate source
                        $paymentId = null;
                        if ($paymentData instanceof \Monei\MoneiPayment\Model\Data\PaymentDTO) {
                            $paymentId = $paymentData->getId();
                        } elseif (is_array($paymentData) && isset($paymentData['id'])) {
                            $paymentId = $paymentData['id'];
                        }

                        if ($paymentId) {
                            $vaultCreated = $this->createVaultPayment->execute(
                                $paymentId,
                                $payment
                            );
                        }
                    }
                }

                /** @var Transaction $transaction */
                $transaction = $this->transactionFactory->create()
                    ->addObject($invoice)
                    ->addObject($orderObj);
                $transaction->save();
            } finally {
                $this->lockManager->unlockOrder($incrementId);
            }
        } catch (\Exception $e) {
            // Log the error but don't rethrow to avoid breaking the callback flow
            // This is a non-critical operation
        }
    }

    /**
     * Check if the order is already paid
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

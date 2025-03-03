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
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Monei\MoneiPayment\Api\OrderLockManagerInterface;
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
     * @var OrderLockManagerInterface
     */
    private OrderLockManagerInterface $orderLockManager;

    /**
     * @var CreateVaultPayment
     */
    private CreateVaultPayment $createVaultPayment;

    /**
     * @param OrderInterfaceFactory $orderFactory
     * @param TransactionFactory $transactionFactory
     * @param OrderLockManagerInterface $orderLockManager
     * @param CreateVaultPayment $createVaultPayment
     */
    public function __construct(
        OrderInterfaceFactory $orderFactory,
        TransactionFactory $transactionFactory,
        OrderLockManagerInterface $orderLockManager,
        CreateVaultPayment $createVaultPayment
    ) {
        $this->createVaultPayment = $createVaultPayment;
        $this->orderLockManager = $orderLockManager;
        $this->transactionFactory = $transactionFactory;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @inheritdoc
     *
     * @param array $data
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

        $this->orderLockManager->lock($incrementId);
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

        $this->orderLockManager->unlock($incrementId);
    }

    /**
     * Check if order is already paid
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isOrderAlreadyPaid(OrderInterface $order): bool
    {
        $payment = $order->getPayment();

        return $payment && $payment->getLastTransId() && $payment->getAmountPaid() && !$order->getTotalDue();
    }
}

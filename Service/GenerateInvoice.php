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
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;
use Psr\Log\LoggerInterface;

/**
 * Generate invoice for order service class.
 */
class GenerateInvoice implements GenerateInvoiceInterface
{
    /** @var OrderInterfaceFactory */
    private $orderFactory;

    /** @var TransactionFactory */
    private $transactionFactory;

    /** @var OrderLockManagerInterface */
    private $orderLockManager;

    /** @var CreateVaultPayment */
    private $createVaultPayment;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param OrderInterfaceFactory $orderFactory
     * @param TransactionFactory $transactionFactory
     * @param OrderLockManagerInterface $orderLockManager
     * @param CreateVaultPayment $createVaultPayment
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderInterfaceFactory $orderFactory,
        TransactionFactory $transactionFactory,
        OrderLockManagerInterface $orderLockManager,
        CreateVaultPayment $createVaultPayment,
        LoggerInterface $logger
    ) {
        $this->createVaultPayment = $createVaultPayment;
        $this->orderLockManager = $orderLockManager;
        $this->transactionFactory = $transactionFactory;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
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

        // Set the MONEI payment id on the order for future reference
        $order->setData('monei_payment_id', $data['id']);
        $this->logger->debug('[Generate invoice] Monei payment ID set on order: ' . $data['id']);

        $invoice = $order->prepareInvoice();
        if (!$invoice->getAllItems()) {
            return;
        }
        $invoice->register()->capture();
        $this->logger->debug('[Generate invoice] Invoice registered and captured');

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
     * Check if the order is already paid.
     *
     * @param OrderInterface $order
     *
     * @return bool
     */
    private function isOrderAlreadyPaid(OrderInterface $order): bool
    {
        $payment = $order->getPayment();

        return $payment && $payment->getLastTransId() && $payment->getAmountPaid() && !$order->getTotalDue();
    }
}

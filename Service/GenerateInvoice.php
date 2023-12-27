<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Magento\Sales\Api\Data\OrderInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\OrderLockManagerInterface;
use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Monei\MoneiPayment\Service\Order\CreateVaultPayment;

/**
 * Generate invoice for order service class
 */
class GenerateInvoice implements GenerateInvoiceInterface
{
    public function __construct(
        private readonly OrderInterfaceFactory $orderFactory,
        private readonly TransactionFactory $transactionFactory,
        private readonly OrderLockManagerInterface $orderLockManager,
        private readonly CreateVaultPayment $createVaultPayment
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(array $data): void
    {
        $incrementId = $data['orderId'];
        /** @var OrderInterface $order */
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if(!$order->getId()){
            return;
        }

        $isOrderLocked = $this->orderLockManager->isLocked($incrementId);
        if ($isOrderLocked || $this->isOrderAlreadyPaid($order)){
            return;
        }

        $this->orderLockManager->lock($incrementId);

        $invoice = $order->prepareInvoice();
        if(!$invoice->getAllItems()){
            return;
        }
        $invoice->register()->capture();
        $order->addRelatedObject($invoice);
        $payment = $order->getPayment();
        if($payment){
            $payment->setLastTransId($data['id']);
            $payment->setCreatedInvoice($invoice);
            if ($order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_SAVE_TOKENIZATION)) {
                $this->createVaultPayment->execute(
                    $data['id'],
                    $payment
                );
            }
        }
        $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder())->save();

        $this->orderLockManager->unlock($incrementId);
    }

    private function isOrderAlreadyPaid(OrderInterface $order): bool
    {
        $payment = $order->getPayment();

        return $payment && $payment->getLastTransId() && $payment->getAmountPaid() && !$order->getTotalDue();
    }
}

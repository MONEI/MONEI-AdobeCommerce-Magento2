<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service;

use Monei\MoneiPayment\Api\Service\GenerateInvoiceInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterfaceFactory;

/**
 * Generate invoice for order service class
 */
class GenerateInvoice implements GenerateInvoiceInterface
{
    /**
     * @var OrderInterfaceFactory
     */
    protected $orderFactory;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @param OrderInterfaceFactory $orderFactory
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        OrderInterfaceFactory $orderFactory,
        TransactionFactory $transactionFactory
    ) {
        $this->orderFactory = $orderFactory;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $data): void
    {
        $order = $this->orderFactory->create()->loadByIncrementId($data['orderId']);
        $payment = $order->getPayment();
        $payment->setLastTransId($data['id']);
        $invoice = $order->prepareInvoice();
        $invoice->register()->capture();
        $order->addRelatedObject($invoice);
        $payment->setCreatedInvoice($invoice);

        $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder())->save();
    }
}

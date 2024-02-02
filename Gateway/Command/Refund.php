<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Magento\Payment\Gateway\CommandInterface;

/**
 * Refund Monei payment command
 */
class Refund implements CommandInterface
{
    /**
     * @var RefundPaymentInterface
     */
    private $refundPaymentService;

    /**
     * @param RefundPaymentInterface $subjectReader
     */
    public function __construct(
        RefundPaymentInterface $refundPaymentService
    ) {
        $this->refundPaymentService = $refundPaymentService;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();
        $data = [
            'paymentId' =>$order->getData('monei_payment_id'),
            'amount' => $commandSubject['amount'] * 100,
            'refundReason' => $payment->getCreditmemo()->getData('refund_reason'),
            'storeId' => (int) $order->getStoreId(),
        ];

        $this->refundPaymentService->execute($data);
    }
}

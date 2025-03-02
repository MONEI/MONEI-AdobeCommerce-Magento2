<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;

/**
 * Refund Monei payment command.
 */
class Refund implements CommandInterface
{
    /** @var RefundPaymentInterface */
    private $refundPaymentService;

    public function __construct(
        RefundPaymentInterface $refundPaymentService
    ) {
        $this->refundPaymentService = $refundPaymentService;
    }

    public function execute(array $commandSubject)
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();
        $data = [
            'paymentId' => $order->getData('monei_payment_id'),
            'amount' => $commandSubject['amount'] * 100,
            'refundReason' => $payment->getCreditmemo()->getData('refund_reason'),
            'storeId' => (int) $order->getStoreId(),
        ];

        $this->refundPaymentService->execute($data);
    }
}

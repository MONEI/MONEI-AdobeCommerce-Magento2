<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;

/**
 * Refund Monei payment command.
 */
class Refund implements CommandInterface
{
    /** @var RefundPaymentInterface */
    private $refundPaymentService;

    /**
     * Constructor.
     *
     * @param RefundPaymentInterface $refundPaymentService Service for processing refunds
     */
    public function __construct(
        RefundPaymentInterface $refundPaymentService
    ) {
        $this->refundPaymentService = $refundPaymentService;
    }

    /**
     * Execute refund command.
     *
     * @param array $commandSubject Command subject containing payment and amount
     *
     * @return void
     */
    public function execute(array $commandSubject)
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();

        // Try to get payment ID from multiple sources
        $paymentId = $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);

        // If not found on order, try to get from payment additional information
        if (empty($paymentId)) {
            $paymentId = $payment->getAdditionalInformation('monei_payment_id');
        }

        // If still not found, try to get from payment's last transaction ID
        if (empty($paymentId)) {
            $paymentId = $payment->getLastTransId();
        }

        // If payment ID is still empty, throw an exception
        if (empty($paymentId)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot process refund: Monei payment ID is missing.')
            );
        }

        $data = [
            'paymentId' => $paymentId,
            'amount' => $commandSubject['amount'], // Amount will be converted to cents in the service
            'refundReason' => $payment->getCreditmemo()->getData('refund_reason') ?? 'requested_by_customer',
            'storeId' => (int) $order->getStoreId(),
        ];

        $this->refundPaymentService->execute($data);
    }
}

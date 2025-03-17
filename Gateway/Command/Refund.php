<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Monei\Model\PaymentRefundReason;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Logger;

/**
 * Refund Monei payment command.
 */
class Refund implements CommandInterface
{
    /**
     * @var RefundPaymentInterface
     */
    private $refundPaymentService;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param RefundPaymentInterface $refundPaymentService Service for processing refunds
     * @param Logger $logger Logger for tracking operations
     */
    public function __construct(
        RefundPaymentInterface $refundPaymentService,
        Logger $logger
    ) {
        $this->refundPaymentService = $refundPaymentService;
        $this->logger = $logger;
    }

    /**
     * Execute refund command.
     *
     * @param array $commandSubject Command subject containing payment and amount
     *
     * @return ResultInterface|null
     * @throws LocalizedException
     */
    public function execute(array $commandSubject): ?ResultInterface
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();

        // Try to get payment ID from multiple sources
        $paymentId = $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);

        // If not found on order, try to get from payment additional information
        if (empty($paymentId)) {
            $paymentId = $payment->getAdditionalInformation(PaymentInfoInterface::PAYMENT_ID);
        }

        // If still not found, try to get from payment's last transaction ID
        if (empty($paymentId)) {
            $paymentId = $payment->getLastTransId();
        }

        // If payment ID is still empty, throw an exception
        if (empty($paymentId)) {
            $this->logger->critical('[Missing payment ID] Cannot refund payment without a valid payment ID');

            throw new LocalizedException(__('Cannot process refund: Monei payment ID is missing.'));
        }

        $data = [
            'paymentId' => $paymentId,
            'amount' => $commandSubject['amount'],  // Amount will be converted to cents in the service
            'refundReason' => $payment->getCreditmemo()->getData('refund_reason') ?? 'requested_by_customer',
            'storeId' => (int) $order->getStoreId(),
        ];

        $this->logger->debug('[Refund payment request]');
        $this->logger->debug(sprintf(
            'Request data: paymentId=%s, amount=%s, reason=%s',
            $paymentId,
            $commandSubject['amount'],
            $data['refundReason']
        ));

        try {
            // Execute the refund request
            $response = $this->refundPaymentService->execute($data);

            // Log the response for debugging
            $this->logger->debug('[Refund payment response]');
            $this->logger->debug(sprintf(
                'Response type: %s',
                is_object($response) ? get_class($response) : gettype($response)
            ));

            // Get the refund ID from the response
            $refundId = $response->getId();

            if ($refundId) {
                // Update payment transaction IDs
                $payment->setTransactionId($refundId);
                $payment->setLastTransId($refundId);

                // Update payment additional information
                $payment->setAdditionalInformation(
                    PaymentInfoInterface::PAYMENT_STATUS,
                    $payment->getCreditmemo()->getBaseGrandTotal() >= $order->getBaseGrandTotal()
                        ? Status::REFUNDED
                        : Status::PARTIALLY_REFUNDED
                );

                $this->logger->info(sprintf(
                    '[Refund transaction ID stored] Order %s, transaction ID: %s',
                    $order->getIncrementId(),
                    $refundId
                ));
            } else {
                $this->logger->warning(sprintf(
                    '[No refund ID found in response] Order %s',
                    $order->getIncrementId()
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[Refund payment error] Order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));

            throw new LocalizedException(__('Error processing refund: %1', $e->getMessage()));
        }

        return null;
    }
}

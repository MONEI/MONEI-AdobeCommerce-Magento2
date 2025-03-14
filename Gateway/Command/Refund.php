<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Monei\MoneiPayment\Api\Data\PaymentInfoInterface;
use Monei\MoneiPayment\Api\Service\RefundPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Status;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param RefundPaymentInterface $refundPaymentService Service for processing refunds
     * @param LoggerInterface $logger Logger for tracking operations
     */
    public function __construct(
        RefundPaymentInterface $refundPaymentService,
        LoggerInterface $logger
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

        // Ensure we have a valid payment ID
        if (empty($paymentId)) {
            $this->logger->logApiError(
                'refund',
                'Cannot refund payment without a valid payment ID',
                ['order_id' => $payment->getOrder()->getIncrementId()]
            );
            throw new LocalizedException(__('Cannot refund payment: Missing payment ID'));
        }

        try {
            // Prepare refund request data
            $data = [
                'amount' => $commandSubject['amount'],
                'paymentId' => $paymentId,
                'reason' => __('Order refund from Magento admin')
            ];

            // Log the refund request
            $this->logger->logApiRequest('refund', [
                'payment_id' => $paymentId,
                'amount' => $commandSubject['amount'],
                'order_id' => $payment->getOrder()->getIncrementId(),
                'currency' => $payment->getOrder()->getOrderCurrencyCode()
            ]);

            // Execute the refund request
            $response = $this->refundPaymentService->execute($data);

            // Log the response for debugging
            $this->logger->logApiResponse('refund', $response);

            // Get the refund ID from the response
            $refundId = $response['refund_id'] ?? null;

            if ($refundId) {
                // Store the refund transaction ID
                $payment->setTransactionId($refundId);
                $payment->setIsTransactionClosed(true);
                $payment->setShouldCloseParentTransaction(true);

                $this->logger->logPaymentEvent(
                    'refund_success',
                    $payment->getOrder()->getIncrementId(),
                    $paymentId,
                    ['refund_id' => $refundId]
                );
            } else {
                $this->logger->logApiError(
                    'refund',
                    'No refund ID found in response',
                    [
                        'order_id' => $payment->getOrder()->getIncrementId(),
                        'payment_id' => $paymentId
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->logger->logApiError(
                'refund',
                'Refund payment error: ' . $e->getMessage(),
                [
                    'order_id' => $payment->getOrder()->getIncrementId(),
                    'payment_id' => $paymentId,
                    'amount' => $commandSubject['amount']
                ]
            );
            throw new LocalizedException(__('Error processing refund: %1', $e->getMessage()));
        }

        return null;
    }
}

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
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Model\Payment\Status;
use Psr\Log\LoggerInterface;

/**
 * Capture Monei payment command.
 */
class Capture implements CommandInterface
{
    /**
     * @var CapturePaymentInterface
     */
    private $capturePaymentService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CapturePaymentInterface $capturePaymentService
     * @param LoggerInterface $logger
     */
    public function __construct(
        CapturePaymentInterface $capturePaymentService,
        LoggerInterface $logger
    ) {
        $this->capturePaymentService = $capturePaymentService;
        $this->logger = $logger;
    }

    /**
     * @param array $commandSubject
     * @throws LocalizedException
     * @return ResultInterface|null
     */
    public function execute(array $commandSubject): ?ResultInterface
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();

        $incrementId = $payment->getOrder()->getIncrementId();

        // Log payment data for debugging
        $this->logger->logPaymentEvent(
            'capture_start',
            $incrementId,
            null,
            $payment->getData()
        );

        // Check if payment is already captured/succeeded
        $additionalInfo = $payment->getAdditionalInformation();
        $moneiStatus = $additionalInfo['monei_payment_status'] ?? $order->getData('monei_status');
        $isAlreadyCaptured = $additionalInfo['monei_is_captured'] ?? false;

        // Log the status values we're checking
        $this->logger->logApiRequest('capture_status_check', [
            'order_id' => $incrementId,
            'monei_status' => $moneiStatus ?? 'null',
            'is_captured' => $isAlreadyCaptured
        ]);

        if ($moneiStatus === Status::SUCCEEDED || (is_string($moneiStatus) && $moneiStatus === 'SUCCEEDED') || $isAlreadyCaptured) {
            // Payment already succeeded or captured, no need to capture again
            $this->logger->logPaymentEvent(
                'payment_already_captured',
                $incrementId,
                null,
                [
                    'status' => $moneiStatus,
                    'is_captured' => $isAlreadyCaptured
                ]
            );

            return null;
        }

        // Get the payment ID from the additional information
        $paymentId = $additionalInfo['monei_payment_id'] ?? null;

        // If not found in additional information, try to get it from the order
        if (empty($paymentId)) {
            $paymentId = $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID);
        }

        // If still not found, try to get it from the payment's last transaction ID
        if (empty($paymentId)) {
            $paymentId = $payment->getLastTransId();
        }

        // If no valid payment ID found, exit with error
        if (empty($paymentId)) {
            $this->logger->logApiError(
                'capture',
                'Cannot capture payment without a valid payment ID',
                ['order_id' => $incrementId]
            );
            throw new LocalizedException(__('Missing payment ID. Cannot capture this payment.'));
        }

        $data = [
            'paymentId' => $paymentId,
            'amount' => $commandSubject['amount']  // Amount will be converted to cents in the service
        ];

        // Log the capture request
        $this->logger->logApiRequest(
            'capture',
            [
                'payment_id' => $paymentId,
                'order_id' => $incrementId,
                'amount' => $commandSubject['amount'],
                'currency' => $order->getOrderCurrencyCode()
            ]
        );

        try {
            // Execute the capture request
            $response = $this->capturePaymentService->execute($data);

            // Log the capture response
            $this->logger->logApiResponse('capture', $response);

            // Get the capture ID from the response
            $captureId = $response->getId();

            if ($captureId) {
                // Update payment transaction IDs
                $payment->setTransactionId($captureId);
                $payment->setLastTransId($captureId);

                // Update payment additional information
                $payment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_IS_CAPTURED, true);
                $payment->setAdditionalInformation(PaymentInfoInterface::PAYMENT_STATUS, Status::SUCCEEDED);

                // Set transaction ID on the invoice if available
                if (
                    isset($commandSubject['payment']) &&
                    method_exists($commandSubject['payment'], 'getCreatedInvoice')
                ) {
                    $invoice = $commandSubject['payment']->getCreatedInvoice();
                    if ($invoice) {
                        $invoice->setTransactionId($captureId);
                        $this->logger->logPaymentEvent(
                            'invoice_transaction_updated',
                            $incrementId,
                            $paymentId,
                            [
                                'invoice_id' => $invoice->getIncrementId(),
                                'transaction_id' => $captureId
                            ]
                        );
                    }
                }

                $this->logger->logPaymentEvent(
                    'capture_transaction_stored',
                    $incrementId,
                    $paymentId,
                    ['transaction_id' => $captureId]
                );
            } else {
                $this->logger->logApiError(
                    'capture',
                    'No capture ID found in response',
                    ['order_id' => $incrementId]
                );
            }
        } catch (\Exception $e) {
            $this->logger->logApiError(
                'capture',
                'Capture payment error: ' . $e->getMessage(),
                ['order_id' => $incrementId]
            );

            throw new LocalizedException(__('Error capturing payment: %1', $e->getMessage()));
        }

        return null;
    }
}

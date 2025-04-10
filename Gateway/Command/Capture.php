<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
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
use Monei\MoneiPayment\Service\Logger;

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
     * @var Logger
     */
    private $logger;

    /**
     * @param CapturePaymentInterface $capturePaymentService
     * @param Logger $logger
     */
    public function __construct(
        CapturePaymentInterface $capturePaymentService,
        Logger $logger
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

        // Log payment data for debugging
        $this->logger->debug('[Capture] Payment data', ['data' => $payment->getData()]);

        // Check if payment is already captured/succeeded
        $additionalInfo = $payment->getAdditionalInformation();
        $moneiStatus = $additionalInfo['monei_payment_status'] ?? $order->getData('monei_status');
        $isAlreadyCaptured = $additionalInfo['monei_is_captured'] ?? false;

        // Log the status values we're checking
        $this->logger->debug(
            '[Capture] Status check',
            [
                'order_id' => $order->getIncrementId(),
                'status' => $moneiStatus ?? 'null',
                'is_captured' => $isAlreadyCaptured ? 'true' : 'false'
            ]
        );

        if ($moneiStatus === Status::SUCCEEDED || (is_string($moneiStatus) && $moneiStatus === 'SUCCEEDED') || $isAlreadyCaptured) {
            // Payment already succeeded or captured, no need to capture again
            $this->logger->info(
                '[Capture] Payment already captured',
                [
                    'order_id' => $order->getIncrementId(),
                    'status' => $moneiStatus,
                    'is_captured' => $isAlreadyCaptured ? 'true' : 'false'
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

        // Ensure we have a valid payment ID
        if (empty($paymentId)) {
            $this->logger->critical('[Missing payment ID] Cannot capture payment without a valid payment ID');

            throw new LocalizedException(__('Cannot capture payment: Missing payment ID'));
        }

        $data = [
            'paymentId' => $paymentId,
            'amount' => $commandSubject['amount']  // Amount will be converted to cents in the service
        ];

        $this->logger->debug('[Capture] Payment request', [
            'order_id' => $order->getIncrementId(),
            'amount' => $commandSubject['amount']
        ]);

        try {
            // Execute the capture request
            $response = $this->capturePaymentService->execute($data);

            // Log the response for debugging
            $this->logger->debug('[Capture] Payment response', [
                'response' => $response
            ]);

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
                if (isset($commandSubject['payment']) &&
                        method_exists($commandSubject['payment'], 'getCreatedInvoice')) {
                    $invoice = $commandSubject['payment']->getCreatedInvoice();
                    if ($invoice) {
                        $invoice->setTransactionId($captureId);
                        $this->logger->info(
                            '[Capture] Invoice created',
                            [
                                'invoice_id' => $invoice->getIncrementId(),
                                'order_id' => $order->getIncrementId()
                            ]
                        );
                    }
                }

                $this->logger->info(
                    '[Capture] Payment captured',
                    ['capture_id' => $captureId]
                );
            } else {
                $this->logger->warning(
                    '[Capture] No capture ID in response',
                    ['order_id' => $order->getIncrementId()]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(
                '[Capture] Capture failed',
                [
                    'payment_id' => $paymentId,
                    'message' => $e->getMessage()
                ]
            );

            throw new LocalizedException(__('Error capturing payment: %1', $e->getMessage()));
        }

        return null;
    }
}

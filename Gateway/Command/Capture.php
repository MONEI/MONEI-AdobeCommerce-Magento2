<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
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

        // Log payment data for debugging
        $this->logger->debug('[Capture payment data]');
        $this->logger->debug(json_encode($payment->getData(), JSON_PRETTY_PRINT));

        // Check if payment is already captured/succeeded
        $additionalInfo = $payment->getAdditionalInformation();
        $moneiStatus = $additionalInfo['monei_payment_status'] ?? $order->getData('monei_status');
        $isAlreadyCaptured = $additionalInfo['monei_is_captured'] ?? false;

        // Log the status values we're checking
        $this->logger->debug(sprintf(
            '[Capture status check] Order %s, monei_status: %s, is_captured: %s',
            $order->getIncrementId(),
            $moneiStatus ?? 'null',
            $isAlreadyCaptured ? 'true' : 'false'
        ));

        if ($moneiStatus === Status::SUCCEEDED || $moneiStatus === 'SUCCEEDED' || $isAlreadyCaptured) {
            // Payment already succeeded or captured, no need to capture again
            $this->logger->info(sprintf(
                '[Payment already captured] Order %s, status: %s, is_captured: %s',
                $order->getIncrementId(),
                $moneiStatus,
                $isAlreadyCaptured ? 'true' : 'false'
            ));

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

        $this->logger->debug('[Capture payment request]');
        $this->logger->debug(json_encode($data, JSON_PRETTY_PRINT));

        $response = $this->capturePaymentService->execute($data);

        if (isset($response['error']) && $response['error'] === true) {
            if (isset($response['errorData']['message'])) {
                throw new LocalizedException(__('{0}', $response['errorData']['message']));
            }

            throw new LocalizedException(__('{0}', $response['errorMessage']));
        }

        return null;
    }
}

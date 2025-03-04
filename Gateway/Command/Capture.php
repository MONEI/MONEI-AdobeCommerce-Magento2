<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Monei\MoneiPayment\Api\Data\OrderInterface as MoneiOrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Capture Monei payment command.
 */
class Capture implements CommandInterface
{
    /** @var CapturePaymentInterface */
    private $capturePaymentService;

    /** @var LoggerInterface */
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
     *
     *
     * @param array $commandSubject
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();

        // Log the full payment object as JSON for debugging purposes
        $paymentData = json_encode($payment, JSON_PRETTY_PRINT);

        // Log payment data for debugging
        $this->logger->debug('[Capture payment data]');
        $this->logger->debug($paymentData);

        // Check if payment is already captured/succeeded
        $moneiStatus = $order->getData('monei_status');
        if ($moneiStatus === Monei::ORDER_STATUS_SUCCEEDED) {
            // Payment already succeeded, no need to capture
            return;
        }

        $data = [
            'paymentId' => $order->getData(MoneiOrderInterface::ATTR_FIELD_MONEI_PAYMENT_ID),
            'amount' => $commandSubject['amount'] * 100
        ];

        $response = $this->capturePaymentService->execute($data);

        if (isset($response['error']) && $response['error'] === true) {
            if (isset($response['errorData']['message'])) {
                throw new LocalizedException(__($response['errorData']['message']));
            }

            throw new LocalizedException(__($response['errorMessage']));
        }
    }
}

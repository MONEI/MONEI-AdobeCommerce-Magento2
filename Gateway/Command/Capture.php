<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Monei\MoneiPayment\Service\Logger;

/**
 * Capture Monei payment command
 */
class Capture
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
     * Execute capture command
     *
     * @param array $commandSubject
     * @return void
     */
    public function execute(array $commandSubject)
    {
        $order = $commandSubject['payment']->getPayment()->getOrder();
        $paymentId = $order->getData('monei_payment_id');

        if (!$paymentId) {
            $this->logger->critical('Cannot capture payment: No payment ID found on order ' . $order->getIncrementId());
            return;
        }

        try {
            $data = [
                'paymentId' => $paymentId,
                'amount' => $commandSubject['amount'] * 100
            ];

            $this->logger->info('Capturing payment ' . $paymentId . ' for amount ' . $commandSubject['amount']);
            $this->capturePaymentService->execute($data);
        } catch (\Exception $e) {
            $this->logger->critical('Error in capture process: ' . $e->getMessage());
        }
    }
}

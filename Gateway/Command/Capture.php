<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;
use Magento\Payment\Gateway\CommandInterface;

/**
 * Capture Monei payment command
 */
class Capture implements CommandInterface
{
    /**
     * @var CapturePaymentInterface
     */
    private $capturePaymentService;

    /**
     * @param CapturePaymentInterface $capturePaymentService
     */
    public function __construct(
        CapturePaymentInterface $capturePaymentService
    ) {
        $this->capturePaymentService = $capturePaymentService;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        $order = $commandSubject['payment']->getPayment()->getOrder();
        $data = [
            'paymentId' => $order->getData('monei_payment_id'),
            'amount' => $commandSubject['amount'] * 100
        ];

        $this->capturePaymentService->execute($data);
    }
}

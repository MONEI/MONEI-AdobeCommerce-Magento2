<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;

/**
 * Capture Monei payment command.
 */
class Capture implements CommandInterface
{
    /** @var CapturePaymentInterface */
    private $capturePaymentService;

    /**
     * Constructor.
     *
     * @param CapturePaymentInterface $capturePaymentService Service for processing captures
     */
    public function __construct(
        CapturePaymentInterface $capturePaymentService
    ) {
        $this->capturePaymentService = $capturePaymentService;
    }

    /**
     * Execute capture command.
     *
     * @param array $commandSubject Command subject containing payment and amount
     * @return void
     */
    public function execute(array $commandSubject)
    {
        $order = $commandSubject['payment']->getPayment()->getOrder();
        $data = [
            'paymentId' => $order->getData('monei_payment_id'),
            'amount' => $commandSubject['amount'] * 100,
        ];

        $this->capturePaymentService->execute($data);
    }
}

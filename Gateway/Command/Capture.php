<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Framework\Exception\LocalizedException;
use Monei\MoneiPayment\Api\Service\CapturePaymentInterface;

/**
 * Capture Monei payment command.
 */
class Capture implements CommandInterface
{
    /** @var CapturePaymentInterface */
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
     * @inheritdoc
     *
     * @param array $commandSubject
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        $order = $commandSubject['payment']->getPayment()->getOrder();
        $data = [
            'paymentId' => $order->getData('monei_payment_id'),
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

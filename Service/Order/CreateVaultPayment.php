<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Order;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Monei\Model\Payment;
use Monei\Model\PaymentPaymentMethod;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class CreateVaultPayment
{
    /**
     * Payment token factory for creating vault payment tokens.
     *
     * @var PaymentTokenFactoryInterface
     */
    private PaymentTokenFactoryInterface $paymentTokenFactory;

    /**
     * Service for retrieving payment information.
     *
     * @var GetPaymentInterface
     */
    private GetPaymentInterface $getPayment;

    /**
     * Constructor.
     *
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param GetPaymentInterface $getPayment
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        GetPaymentInterface $getPayment
    ) {
        $this->getPayment = $getPayment;
        $this->paymentTokenFactory = $paymentTokenFactory;
    }

    /**
     * Execute vault payment creation.
     *
     * @param string $moneiPaymentId
     * @param OrderPaymentInterface $payment
     */
    public function execute(string $moneiPaymentId, OrderPaymentInterface &$payment): bool
    {
        if (Monei::CARD_CODE !== $payment->getMethod()) {
            return false;
        }

        /** @var Payment $moneiPayment */
        $moneiPayment = $this->getPayment->execute($moneiPaymentId);
        $paymentToken = $this->paymentTokenFactory->create();

        /** @var PaymentPaymentMethod $paymentMethod */
        $paymentMethod = $moneiPayment->getPaymentMethod();
        if (!$paymentMethod) {
            return false;
        }

        $detailsCard = $paymentMethod->getCard();
        if (!$detailsCard) {
            return false;
        }

        $paymentToken->setGatewayToken($moneiPayment->getPaymentToken());
        $paymentToken->setType(Monei::VAULT_TYPE);

        $expiration = $detailsCard->getExpiration();
        $paymentToken->setExpiresAt(date('Y-m-d h:i:s', strtotime('+1 month', $expiration)));

        $paymentToken->setTokenDetails(
            json_encode(
                [
                    'type' => $detailsCard->getType() ?? '',
                    'brand' => $detailsCard->getBrand() ?? '',
                    'name' => $detailsCard->getCardholderName() ?? '',
                    'last4' => $detailsCard->getLast4() ?? '',
                    'expiration_date' => date('m/Y', $expiration) ?? '',
                ]
            )
        );
        if ($payment->getExtensionAttributes()) {
            $payment->getExtensionAttributes()->setVaultPaymentToken($paymentToken);
        }

        return true;
    }
}

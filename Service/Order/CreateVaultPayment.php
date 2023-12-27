<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Order;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class CreateVaultPayment
{
    public function __construct(
        private readonly PaymentTokenFactoryInterface $paymentTokenFactory,
        private readonly GetPaymentInterface          $getPayment
    )
    {
    }

    public function execute(string $moneiPaymentId, OrderPaymentInterface &$payment): void
    {
        $moneiPayment = $this->getPayment->execute($moneiPaymentId);
        $paymentToken = $this->paymentTokenFactory->create();
        $detailsCard = $moneiPayment['paymentMethod']['card'];

        $paymentToken->setGatewayToken($moneiPayment['paymentToken']);
        $paymentToken->setType(Monei::VAULT_TYPE);
        $paymentToken->setExpiresAt(date('Y-m-d h:i:s', strtotime('+1 month', $detailsCard['expiration'])));
        $paymentToken->setTokenDetails(
            \json_encode(
                [
                    'type' => $detailsCard['type'] ?? '',
                    'brand' => $detailsCard['brand'] ?? '',
                    'name' => $detailsCard['cardholderName'] ?? '',
                    'last4' => $detailsCard['last4'] ?? '',
                    'expiration_date' => date('m/Y', $detailsCard['expiration']) ?? ''
                ]
            )
        );
        $payment->getExtensionAttributes()?->setVaultPaymentToken($paymentToken);
    }
}

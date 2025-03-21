<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Order;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Monei\Model\Payment;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Logger;

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
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param GetPaymentInterface $getPayment
     * @param Logger $logger
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        GetPaymentInterface $getPayment,
        Logger $logger
    ) {
        $this->getPayment = $getPayment;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->logger = $logger;
    }

    /**
     * Execute vault payment creation.
     *
     * @param string $moneiPaymentId
     * @param OrderPaymentInterface $payment
     */
    public function execute(string $moneiPaymentId, OrderPaymentInterface &$payment): bool
    {
        $this->logger->debug('[Vault] Starting tokenization process', [
            'payment_id' => $moneiPaymentId,
            'payment_method' => $payment->getMethod()
        ]);

        if (Monei::CARD_CODE !== $payment->getMethod()) {
            $this->logger->debug('[Vault] Payment method is not a card payment, skipping tokenization', [
                'method' => $payment->getMethod(),
                'expected' => Monei::CARD_CODE
            ]);

            return false;
        }

        try {
            /** @var Payment $moneiPayment */
            $moneiPayment = $this->getPayment->execute($moneiPaymentId);

            $this->logger->debug('[Vault] Payment object retrieved', [
                'payment_id' => $moneiPayment->getId(),
                'status' => $moneiPayment->getStatus()
            ]);

            // Get payment token from the payment object
            $gatewayToken = $moneiPayment->getPaymentToken();
            if (empty($gatewayToken)) {
                $this->logger->debug('[Vault] Payment token is empty');

                return false;
            }

            // Get payment method
            $paymentMethod = $moneiPayment->getPaymentMethod();
            if (!$paymentMethod) {
                $this->logger->debug('[Vault] Payment method is null');

                return false;
            }

            $this->logger->debug('[Vault] Payment method retrieved');

            // Create Magento vault token
            $paymentToken = $this->paymentTokenFactory->create();
            $paymentToken->setGatewayToken($gatewayToken);
            $paymentToken->setType(Monei::VAULT_TYPE);

            // Get card details
            $card = $paymentMethod->getCard();

            if (!$card) {
                $this->logger->debug('[Vault] Card details are missing');

                return false;
            }

            // Get expiration date
            $expiration = $card->getExpiration();

            if (!$expiration) {
                $this->logger->debug('[Vault] Card expiration is empty');

                return false;
            }

            // Set expiration date for the token
            $expiresAt = date('Y-m-d h:i:s', strtotime('+1 month', $expiration));
            $paymentToken->setExpiresAt($expiresAt);

            $this->logger->debug('[Vault] Setting token expiration', [
                'expiration_timestamp' => $expiration,
                'expires_at' => $expiresAt
            ]);

            // Build token details from card properties
            $cardType = $card->getType();
            $cardBrand = $card->getBrand();
            $cardholderName = $card->getCardholderName();
            $last4 = $card->getLast4();

            $tokenDetails = [
                'type' => $cardType,
                'brand' => $cardBrand,
                'name' => $cardholderName,
                'last4' => $last4,
                'expiration_date' => date('m/Y', $expiration),
            ];

            $this->logger->debug('[Vault] Setting token details', [
                'token_details' => $tokenDetails
            ]);

            $paymentToken->setTokenDetails(json_encode($tokenDetails));

            // Set the token on the payment
            $extensionAttributes = $payment->getExtensionAttributes();
            if (!$extensionAttributes) {
                $this->logger->debug('[Vault] Payment extension attributes are null');
                return false;
            }

            if (!method_exists($extensionAttributes, 'setVaultPaymentToken')) {
                $this->logger->debug('[Vault] Payment extension attributes do not support vault payment tokens');
                return false;
            }

            $extensionAttributes->setVaultPaymentToken($paymentToken);

            $this->logger->debug('[Vault] Payment token successfully created and assigned');

            return true;
        } catch (\Exception $e) {
            $this->logger->debug('[Vault] Exception during tokenization process', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }
}

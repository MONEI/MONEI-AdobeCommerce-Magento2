<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Info;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Info;
use Monei\Model\Payment;
use Monei\Model\PaymentPaymentMethod;
use Monei\MoneiPayment\Api\Service\GetPaymentInterface;

/**
 * Monei payment info block.
 */
class Monei extends Info
{
    /**
     * Payment information fields allowed to be displayed.
     */
    private const INFO_PAY_ALLOWED = [
        'last4',
        'brand',
        'phoneNumber',
    ];

    /**
     * Monei template.
     *
     * @var string
     */
    protected $_template = 'Monei_MoneiPayment::info/monei.phtml';

    /**
     * Service for retrieving payment information from Monei.
     *
     * @var GetPaymentInterface
     */
    private $paymentService;

    /**
     * Constructor.
     *
     * @param GetPaymentInterface $paymentService
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        GetPaymentInterface $paymentService,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->paymentService = $paymentService;
    }

    /**
     * Get payment information.
     *
     * @throws LocalizedException
     *
     * @return array|null
     */
    public function getPaymentInfo()
    {
        if (!$this->getInfo() || !$this->getInfo()->getOrder()) {
            return null;
        }

        $monei_payment_id = $this->getInfo()->getOrder()->getData('monei_payment_id');
        if (!$monei_payment_id) {
            return null;
        }

        /** @var Payment $payment */
        $payment = $this->paymentService->execute($monei_payment_id);
        $paymentMethod = $payment->getPaymentMethod();

        if (!$paymentMethod instanceof PaymentPaymentMethod) {
            return null;
        }

        return $this->processPaymentMethodObject($paymentMethod);
    }

    /**
     * Process payment method object.
     *
     * @param PaymentPaymentMethod $paymentMethod Payment method object
     *
     * @return array|null Processed payment method data
     */
    private function processPaymentMethodObject(PaymentPaymentMethod $paymentMethod): ?array
    {
        $result = [];

        // Store the payment method type
        $result['method'] = $paymentMethod->getMethod();

        // Process card data if available
        $card = $paymentMethod->getCard();
        if ($card) {
            // Basic card details
            $result['brand'] = $card->getBrand();
            $result['last4'] = $card->getLast4();
            $result['type'] = $card->getType();

            // Advanced card details
            if ($card->getExpiration()) {
                $result['expiration'] = date('m/y', $card->getExpiration());
            }
            if ($card->getCardholderName()) {
                $result['cardholderName'] = $card->getCardholderName();
            }
            if ($card->getCountry()) {
                $result['country'] = $card->getCountry();
            }
            if ($card->getTokenizationMethod()) {
                $result['tokenizationMethod'] = $card->getTokenizationMethod();
            }
            if ($card->getCardholderEmail()) {
                $result['cardholderEmail'] = $card->getCardholderEmail();
            }

            // 3DS information
            if ($card->getThreeDSecure()) {
                $result['threeDSecure'] = $card->getThreeDSecure();
                if ($card->getThreeDSecureVersion()) {
                    $result['threeDSecureVersion'] = $card->getThreeDSecureVersion();
                }
                if ($card->getThreeDSecureFlow()) {
                    $result['threeDSecureFlow'] = $card->getThreeDSecureFlow();
                }
            }
        }

        // Process bizum data if available
        $bizum = $paymentMethod->getBizum();
        if ($bizum) {
            if ($bizum->getPhoneNumber()) {
                $result['phoneNumber'] = $bizum->getPhoneNumber();
            }
            // Add other bizum specific fields if available
        }

        // Process PayPal data if available
        $paypal = $paymentMethod->getPaypal();
        if ($paypal) {
            $result['method'] = 'paypal';
            // Add PayPal specific fields if needed
        }

        // Process MBWay data if available
        $mbway = $paymentMethod->getMbway();
        if ($mbway) {
            $result['method'] = 'mbway';
            if ($mbway->getPhoneNumber()) {
                $result['phoneNumber'] = $mbway->getPhoneNumber();
            }
        }

        // Process SEPA data if available
        $sepa = $paymentMethod->getSepa();
        if ($sepa) {
            $result['method'] = 'sepa';
            if ($sepa->getIban()) {
                $result['iban'] = $this->maskIban($sepa->getIban());
            }
            if ($sepa->getName()) {
                $result['accountName'] = $sepa->getName();
            }
        }

        return $result;
    }

    /**
     * Mask an IBAN for display purposes
     *
     * @param string $iban
     * @return string
     */
    private function maskIban(string $iban): string
    {
        if (strlen($iban) <= 8) {
            return str_repeat('*', strlen($iban));
        }

        // Keep first 4 and last 4 characters visible
        return substr($iban, 0, 4) . str_repeat('*', strlen($iban) - 8) . substr($iban, -4);
    }

    /**
     * Process payment method data.
     *
     * @deprecated Use processPaymentMethodObject instead
     * @see processPaymentMethodObject() For the new implementation using SDK objects
     * @param array|null $paymentMethodData Payment method data
     *
     * @return array|null Processed payment method data
     */
    private function processPaymentMethodData(?array $paymentMethodData): ?array
    {
        if (!$paymentMethodData) {
            return null;
        }

        $result = [];
        foreach ($paymentMethodData as $payKey => $payValue) {
            if (!\is_array($payValue)) {
                $result[$payKey] = $payValue;

                continue;
            }

            foreach ($payValue as $key => $value) {
                $result[$key] = $key === 'expiration' ? date('m/y', $value) : $value;
            }
        }

        return $result;
    }

    /**
     * Get payment title.
     *
     * @throws LocalizedException
     *
     * @return string|null
     */
    public function getPaymentTitle()
    {
        if ($this->getInfo() && $this->getInfo()->getOrder()) {
            return $this->getMethod()->getConfigData('title', $this->getInfo()->getOrder()->getStoreId());
        }

        return null;
    }

    /**
     * Get list of allowed payment information fields.
     */
    public function getInfoPayAllowed(): array
    {
        return self::INFO_PAY_ALLOWED;
    }
}

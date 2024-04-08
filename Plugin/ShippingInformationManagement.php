<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement as MagentoShippingInformationManagement;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class ShippingInformationManagement
{
    private const PAYMENT_METHODS_MONEI = [
        Monei::CODE,
        Monei::CARD_CODE,
        Monei::CC_VAULT_CODE,
        Monei::BIZUM_CODE,
        Monei::GOOGLE_APPLE_CODE
    ];

    private MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig;

    public function __construct(MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig)
    {
        $this->moneiPaymentModuleConfig = $moneiPaymentModuleConfig;
    }

    public function aroundSaveAddressInformation(
        MagentoShippingInformationManagement $subject,
        callable                             $proceed,
                                             $cartId,
        ShippingInformationInterface         $addressInformation
    )
    {
        $hasChange = false;
        /** @var PaymentDetailsInterface $paymentDetails */
        $paymentDetails = $proceed($cartId, $addressInformation);
        $paymentMethods = $paymentDetails->getPaymentMethods();
        $accountId = $this->moneiPaymentModuleConfig->getAccountId();
        $apiKey = $this->moneiPaymentModuleConfig->getApiKey();

        if (!empty($accountId) && !empty($apiKey)) {
            return $paymentDetails;
        }

        foreach ($paymentMethods as $index => $paymentMethod) {
            if (in_array($paymentMethod->getCode(), self::PAYMENT_METHODS_MONEI, true)) {
                unset($paymentMethods[$index]);
                $hasChange = true;
            }
        }

        if ($hasChange) {
            $paymentDetails->setPaymentMethods($paymentMethods);
        }

        return $paymentDetails;
    }
}

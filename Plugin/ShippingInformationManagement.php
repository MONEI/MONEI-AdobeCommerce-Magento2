<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement as MagentoShippingInformationManagement;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Service\Shared\GetAvailableMoneiPaymentMethodsByCountry;
use Monei\MoneiPayment\Service\Shared\GetMoneiPaymentCodesByMagentoPaymentCode;

/**
 * Plugin for ShippingInformationManagement to filter payment methods based on shipping address.
 */
class ShippingInformationManagement
{
    private MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig;

    private GetAvailableMoneiPaymentMethodsByCountry $getAvailableMoneiPaymentMethodsByCountry;

    private GetMoneiPaymentCodesByMagentoPaymentCode $getMoneiPaymentCodesByMagentoPaymentCode;

    public function __construct(
        MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig,
        GetAvailableMoneiPaymentMethodsByCountry $getAvailableMoneiPaymentMethodsByCountry,
        GetMoneiPaymentCodesByMagentoPaymentCode $getMoneiPaymentCodesByMagentoPaymentCode
    ) {
        $this->moneiPaymentModuleConfig = $moneiPaymentModuleConfig;
        $this->getAvailableMoneiPaymentMethodsByCountry = $getAvailableMoneiPaymentMethodsByCountry;
        $this->getMoneiPaymentCodesByMagentoPaymentCode = $getMoneiPaymentCodesByMagentoPaymentCode;
    }

    /**
     * Filter payment methods based on shipping address.
     *
     * @param int $cartId
     *
     * @return PaymentDetailsInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSaveAddressInformation(
        MagentoShippingInformationManagement $subject,
        callable $proceed,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $paymentDetails = $proceed($cartId, $addressInformation);

        return $this->filterPaymentMethods($paymentDetails, $addressInformation);
    }

    /**
     * Filter payment methods based on MONEI configuration.
     */
    private function filterPaymentMethods(
        PaymentDetailsInterface $paymentDetails,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        $accountId = $this->moneiPaymentModuleConfig->getAccountId();
        $apiKey = $this->moneiPaymentModuleConfig->getApiKey();

        if (empty($accountId) || empty($apiKey)) {
            return $this->disableMoneiPaymentMethods($paymentDetails);
        }

        return $this->disablePaymentMethodsByShippingAddress($paymentDetails, $addressInformation);
    }

    /**
     * Disable payment methods not available for the shipping address country.
     */
    private function disablePaymentMethodsByShippingAddress(
        PaymentDetailsInterface $paymentDetails,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        $paymentMethods = $paymentDetails->getPaymentMethods();
        $shippingAddress = $addressInformation->getShippingAddress();
        $availableMoneiPaymentMethodsByCountry = $this->getAvailableMoneiPaymentMethodsByCountry->execute(
            $shippingAddress->getCountryId()
        );

        $filteredPaymentMethods = [];
        foreach ($paymentMethods as $paymentMethod) {
            $moneiPaymentCodes = $this->getMoneiPaymentCodesByMagentoPaymentCode->execute(
                $paymentMethod->getCode()
            );
            if (!$moneiPaymentCodes
                || $this->isPaymentMethodAllowed($moneiPaymentCodes, $availableMoneiPaymentMethodsByCountry)
            ) {
                $filteredPaymentMethods[] = $paymentMethod;
            }
        }

        $paymentDetails->setPaymentMethods($filteredPaymentMethods);

        return $paymentDetails;
    }

    /**
     * Check if payment method is allowed for the country.
     */
    private function isPaymentMethodAllowed(
        array $moneiPaymentCodes,
        array $availableMoneiPaymentMethodsByCountry
    ): bool {
        foreach ($moneiPaymentCodes as $moneiPaymentCode) {
            if (\in_array($moneiPaymentCode, $availableMoneiPaymentMethodsByCountry, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Disable all MONEI payment methods.
     */
    private function disableMoneiPaymentMethods(PaymentDetailsInterface $paymentDetails): PaymentDetailsInterface
    {
        $paymentMethods = $paymentDetails->getPaymentMethods();
        $filteredPaymentMethods = array_filter($paymentMethods, function ($paymentMethod) {
            return !\in_array($paymentMethod->getCode(), Monei::PAYMENT_METHODS_MONEI, true);
        });
        $paymentDetails->setPaymentMethods($filteredPaymentMethods);

        return $paymentDetails;
    }
}

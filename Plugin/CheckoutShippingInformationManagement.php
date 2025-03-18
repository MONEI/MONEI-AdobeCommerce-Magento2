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
use Monei\MoneiPayment\Service\Shared\CountryPaymentMethods;
use Monei\MoneiPayment\Service\Shared\PaymentMethodMap;

/**
 * Plugin for ShippingInformationManagement to filter payment methods based on shipping address.
 */
class CheckoutShippingInformationManagement
{
    /**
     * Monei payment module configuration.
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig;

    /**
     * Service to get available Monei payment methods by country.
     *
     * @var CountryPaymentMethods
     */
    private CountryPaymentMethods $countryPaymentMethods;

    /**
     * Service to get Monei payment codes by Magento payment code.
     *
     * @var PaymentMethodMap
     */
    private PaymentMethodMap $paymentMethodMap;

    /**
     * Constructor for ShippingInformationManagement plugin.
     *
     * @param MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig
     *     Configuration for Monei payment module
     * @param CountryPaymentMethods $countryPaymentMethods
     *     Service to get available payment methods by country
     * @param PaymentMethodMap $paymentMethodMap
     *     Service to get Monei payment codes
     */
    public function __construct(
        MoneiPaymentModuleConfigInterface $moneiPaymentModuleConfig,
        CountryPaymentMethods $countryPaymentMethods,
        PaymentMethodMap $paymentMethodMap
    ) {
        $this->moneiPaymentModuleConfig = $moneiPaymentModuleConfig;
        $this->countryPaymentMethods = $countryPaymentMethods;
        $this->paymentMethodMap = $paymentMethodMap;
    }

    /**
     * Filter payment methods based on shipping address.
     *
     * @param MagentoShippingInformationManagement $subject
     * @param callable $proceed
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
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
     *
     * @param PaymentDetailsInterface $paymentDetails
     * @param ShippingInformationInterface $addressInformation
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
     *
     * @param PaymentDetailsInterface $paymentDetails
     * @param ShippingInformationInterface $addressInformation
     */
    private function disablePaymentMethodsByShippingAddress(
        PaymentDetailsInterface $paymentDetails,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        $paymentMethods = $paymentDetails->getPaymentMethods();
        $shippingAddress = $addressInformation->getShippingAddress();
        $availableMoneiPaymentMethodsByCountry = $this->countryPaymentMethods->execute(
            $shippingAddress->getCountryId()
        );

        $filteredPaymentMethods = [];
        foreach ($paymentMethods as $paymentMethod) {
            $moneiPaymentCodes = $this->paymentMethodMap->execute(
                $paymentMethod->getCode()
            );
            if (!$moneiPaymentCodes ||
                $this->isPaymentMethodAllowed($moneiPaymentCodes, $availableMoneiPaymentMethodsByCountry)
            ) {
                $filteredPaymentMethods[] = $paymentMethod;
            }
        }

        $paymentDetails->setPaymentMethods($filteredPaymentMethods);

        return $paymentDetails;
    }

    /**
     * Check if payment method is allowed for the country.
     *
     * @param array $moneiPaymentCodes
     * @param array $availableMoneiPaymentMethodsByCountry
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
     *
     * @param PaymentDetailsInterface $paymentDetails
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

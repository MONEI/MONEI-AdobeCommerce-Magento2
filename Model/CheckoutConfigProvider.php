<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\AllMoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiBizumPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Block\Monei\Customer\CardRenderer;
use Monei\MoneiPayment\Model\Config\Source\Mode;
use Monei\MoneiPayment\Model\Payment\Monei;
use Monei\MoneiPayment\Model\Payment\Status;
use Monei\MoneiPayment\Service\Shared\ApplePayAvailability;
use Monei\MoneiPayment\Service\Shared\GooglePayAvailability;

/**
 * Provides config data for payment.
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{
    /**
     * URL builder interface for creating URLs.
     *
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * Configuration settings for the main Monei payment methods.
     *
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moneiPaymentConfig;

    /**
     * Configuration settings specific to Monei card payments.
     *
     * @var MoneiCardPaymentModuleConfigInterface
     */
    private MoneiCardPaymentModuleConfigInterface $moneiCardPaymentConfig;

    /**
     * Configuration settings for Google Pay and Apple Pay payment methods.
     *
     * @var MoneiGoogleApplePaymentModuleConfigInterface
     */
    private MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentConfig;

    /**
     * Configuration settings specific to Bizum payment method.
     *
     * @var MoneiBizumPaymentModuleConfigInterface
     */
    private MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig;

    /**
     * Interface for accessing store-related information.
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * All Monei payment configurations.
     *
     * @var AllMoneiPaymentModuleConfigInterface
     */
    private AllMoneiPaymentModuleConfigInterface $allMoneiPaymentModuleConfig;

    /**
     * Google Pay availability checker.
     *
     * @var GooglePayAvailability
     */
    private GooglePayAvailability $googlePayAvailability;

    /**
     * Apple Pay availability checker.
     *
     * @var ApplePayAvailability
     */
    private ApplePayAvailability $applePayAvailability;

    /**
     * Constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param AllMoneiPaymentModuleConfigInterface $allMoneiPaymentModuleConfig
     * @param MoneiPaymentModuleConfigInterface $moneiPaymentConfig
     * @param MoneiCardPaymentModuleConfigInterface $moneiCardPaymentConfig
     * @param MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentConfig
     * @param MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig
     * @param GooglePayAvailability $googlePayAvailability
     * @param ApplePayAvailability $applePayAvailability
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        UrlInterface $urlBuilder,
        AllMoneiPaymentModuleConfigInterface $allMoneiPaymentModuleConfig,
        MoneiPaymentModuleConfigInterface $moneiPaymentConfig,
        MoneiCardPaymentModuleConfigInterface $moneiCardPaymentConfig,
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentConfig,
        MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig,
        GooglePayAvailability $googlePayAvailability,
        ApplePayAvailability $applePayAvailability,
        StoreManagerInterface $storeManager
    ) {
        $this->allMoneiPaymentModuleConfig = $allMoneiPaymentModuleConfig;
        $this->moneiGoogleApplePaymentConfig = $moneiGoogleApplePaymentConfig;
        $this->moneiBizumPaymentModuleConfig = $moneiBizumPaymentModuleConfig;
        $this->moneiCardPaymentConfig = $moneiCardPaymentConfig;
        $this->googlePayAvailability = $googlePayAvailability;
        $this->applePayAvailability = $applePayAvailability;
        $this->moneiPaymentConfig = $moneiPaymentConfig;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
    }

    /**
     * Get configuration for checkout.
     *
     * Provides configuration data needed for the checkout process.
     * Includes account ID, API key, mode settings, and payment method configurations.
     *
     * @return array Configuration data for checkout
     */
    public function getConfig(): array
    {
        $storeId = $this->getStoreId();

        return [
            'moneiAccountId' => $this->moneiPaymentConfig->getAccountId($storeId),
            'moneiApiKey' => $this->moneiPaymentConfig->getApiKey($storeId),
            'moneiPaymentIsEnabled' => $this->allMoneiPaymentModuleConfig->isAnyPaymentEnabled($storeId),
            'isMoneiTestMode' => Mode::MODE_TEST === $this->moneiPaymentConfig->getMode($storeId),
            'moneiLanguage' => $this->moneiPaymentConfig->getLanguage($storeId),
            'payment' => [
                Monei::REDIRECT_CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
                    'failOrderStatus' => [
                        Status::EXPIRED,
                        Status::CANCELED,
                        Status::FAILED,
                    ],
                ],
                Monei::CARD_CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
                    'failOrderStatus' => [
                        Status::EXPIRED,
                        Status::CANCELED,
                        Status::FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($storeId),
                    'isEnabledTokenization' => $this->moneiCardPaymentConfig->isEnabledTokenization($storeId),
                    'ccVaultCode' => Monei::CC_VAULT_CODE,
                    'jsonStyle' => $this->moneiCardPaymentConfig->getJsonStyle($storeId),
                ],
                Monei::BIZUM_CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
                    'failOrderStatus' => [
                        Status::EXPIRED,
                        Status::CANCELED,
                        Status::FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($storeId),
                    'jsonStyle' => $this->moneiBizumPaymentModuleConfig->getJsonStyle($storeId),
                ],
                Monei::GOOGLE_APPLE_CODE => [
                    'isEnabledGooglePay' => $this->googlePayAvailability->execute(),
                    'isEnabledApplePay' => $this->applePayAvailability->execute(),
                    'googleTitle' => $this->moneiGoogleApplePaymentConfig->getGoogleTitle($storeId),
                    'appleTitle' => $this->moneiGoogleApplePaymentConfig->getAppleTitle($storeId),
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
                    'failOrderStatus' => [
                        Status::EXPIRED,
                        Status::CANCELED,
                        Status::FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($storeId),
                    'jsonStyle' => $this->moneiGoogleApplePaymentConfig->getJsonStyle($storeId),
                ],
            ],
            'vault' => [
                Monei::CC_VAULT_CODE => [
                    'card_icons' => CardRenderer::ICON_TYPE_BY_BRAND,
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
                    'failOrderStatus' => [
                        Status::EXPIRED,
                        Status::CANCELED,
                        Status::FAILED,
                    ],
                    'methodCardCode' => Monei::CARD_CODE,
                ],
            ],
        ];
    }

    /**
     * Get current store ID.
     */
    private function getStoreId(): int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException $e) {
            return 0;
        }
    }
}

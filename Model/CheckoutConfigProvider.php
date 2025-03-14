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
use Monei\MoneiPayment\Api\Config\MoneiPaypalPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Service\GetPaymentMethodsInterface;
use Monei\MoneiPayment\Helper\PaymentMethod;
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
     * Configuration settings specific to PayPal payment method.
     *
     * @var MoneiPaypalPaymentModuleConfigInterface
     */
    private MoneiPaypalPaymentModuleConfigInterface $moneiPaypalPaymentModuleConfig;

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
     * Payment method helper for icons and formatting.
     *
     * @var PaymentMethod
     */
    private PaymentMethod $paymentMethodHelper;

    /**
     * Service to get available payment methods
     *
     * @var GetPaymentMethodsInterface
     */
    private GetPaymentMethodsInterface $getPaymentMethods;

    /**
     * Constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param AllMoneiPaymentModuleConfigInterface $allMoneiPaymentModuleConfig
     * @param MoneiPaymentModuleConfigInterface $moneiPaymentConfig
     * @param MoneiCardPaymentModuleConfigInterface $moneiCardPaymentConfig
     * @param MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentConfig
     * @param MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig
     * @param MoneiPaypalPaymentModuleConfigInterface $moneiPaypalPaymentModuleConfig
     * @param GooglePayAvailability $googlePayAvailability
     * @param ApplePayAvailability $applePayAvailability
     * @param StoreManagerInterface $storeManager
     * @param PaymentMethod $paymentMethodHelper
     * @param GetPaymentMethodsInterface $getPaymentMethods
     */
    public function __construct(
        UrlInterface $urlBuilder,
        AllMoneiPaymentModuleConfigInterface $allMoneiPaymentModuleConfig,
        MoneiPaymentModuleConfigInterface $moneiPaymentConfig,
        MoneiCardPaymentModuleConfigInterface $moneiCardPaymentConfig,
        MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentConfig,
        MoneiBizumPaymentModuleConfigInterface $moneiBizumPaymentModuleConfig,
        MoneiPaypalPaymentModuleConfigInterface $moneiPaypalPaymentModuleConfig,
        GooglePayAvailability $googlePayAvailability,
        ApplePayAvailability $applePayAvailability,
        StoreManagerInterface $storeManager,
        PaymentMethod $paymentMethodHelper,
        GetPaymentMethodsInterface $getPaymentMethods
    ) {
        $this->allMoneiPaymentModuleConfig = $allMoneiPaymentModuleConfig;
        $this->moneiGoogleApplePaymentConfig = $moneiGoogleApplePaymentConfig;
        $this->moneiBizumPaymentModuleConfig = $moneiBizumPaymentModuleConfig;
        $this->moneiCardPaymentConfig = $moneiCardPaymentConfig;
        $this->moneiPaypalPaymentModuleConfig = $moneiPaypalPaymentModuleConfig;
        $this->googlePayAvailability = $googlePayAvailability;
        $this->applePayAvailability = $applePayAvailability;
        $this->moneiPaymentConfig = $moneiPaymentConfig;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->getPaymentMethods = $getPaymentMethods;
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

        $config = [
            'moneiAccountId' => $this->moneiPaymentConfig->getAccountId($storeId),
            'moneiApiKey' => $this->moneiPaymentConfig->getApiKey($storeId),
            'moneiPaymentIsEnabled' => $this->allMoneiPaymentModuleConfig->isAnyPaymentEnabled($storeId),
            'isMoneiTestMode' => Mode::MODE_TEST === $this->moneiPaymentConfig->getMode($storeId),
            'moneiLanguage' => $this->moneiPaymentConfig->getLanguage($storeId),
            'moneiPaymentMethods' => [
                'ALIPAY' => 'alipay',
                'APPLE_PAY' => 'applePay',
                'BANCONTACT' => 'bancontact',
                'BIZUM' => 'bizum',
                'BLIK' => 'blik',
                'CARD' => 'card',
                'CARD_PRESENT' => 'cardPresent',
                'CLICK_TO_PAY' => 'clickToPay',
                'COFIDIS' => 'cofidis',
                'COFIDIS_LOAN' => 'cofidisLoan',
                'EPS' => 'eps',
                'GIROPAY' => 'giropay',
                'GOOGLE_PAY' => 'googlePay',
                'I_DEAL' => 'iDeal',
                'KLARNA' => 'klarna',
                'MBWAY' => 'mbway',
                'MULTIBANCO' => 'multibanco',
                'PAYPAL' => 'paypal',
                'SEPA' => 'sepa',
                'SOFORT' => 'sofort',
                'TRUSTLY' => 'trustly'
            ],
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
                    'icon' => $this->paymentMethodHelper->getIconFromPaymentType('card'),
                    'icons' => $this->getCardIcons(),
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
                    'icon' => $this->paymentMethodHelper->getIconFromPaymentType('bizum'),
                    'iconDimensions' => $this->paymentMethodHelper->getPaymentMethodDimensions('bizum'),
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
                    'googlePayIcon' => $this->paymentMethodHelper->getIconFromPaymentType('google_pay'),
                    'googlePayDimensions' => $this->paymentMethodHelper->getPaymentMethodDimensions('google_pay'),
                    'applePayIcon' => $this->paymentMethodHelper->getIconFromPaymentType('apple_pay'),
                    'applePayDimensions' => $this->paymentMethodHelper->getPaymentMethodDimensions('apple_pay'),
                    'availablePaymentMethods' => $this->getAvailablePaymentMethodsList($storeId),
                ],
                Monei::MULTIBANCO_REDIRECT_CODE => [
                    'icon' => $this->paymentMethodHelper->getIconFromPaymentType('multibanco'),
                    'iconDimensions' => $this->paymentMethodHelper->getPaymentMethodDimensions('multibanco'),
                ],
                Monei::MBWAY_REDIRECT_CODE => [
                    'icon' => $this->paymentMethodHelper->getIconFromPaymentType('mbway'),
                    'iconDimensions' => $this->paymentMethodHelper->getPaymentMethodDimensions('mbway'),
                ],
                Monei::PAYPAL_CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'completeUrl' => $this->urlBuilder->getUrl('monei/payment/complete'),
                    'failOrderStatus' => [
                        Status::EXPIRED,
                        Status::CANCELED,
                        Status::FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($storeId),
                    'jsonStyle' => $this->moneiPaypalPaymentModuleConfig->getJsonStyle($storeId),
                    'icon' => $this->paymentMethodHelper->getIconFromPaymentType('paypal'),
                    'iconDimensions' => $this->paymentMethodHelper->getPaymentMethodDimensions('paypal'),
                ]
            ],
            'vault' => [
                Monei::CC_VAULT_CODE => [
                    'icons' => $this->getCardIcons(),
                    'card_icons' => $this->getCardIconTypeMapping(),
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

        return $config;
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

    /**
     * Get mapping between card types and icon codes for the vault
     *
     * @return array
     */
    private function getCardIconTypeMapping(): array
    {
        $mapping = [];
        $availableBrands = $this->getAvailableCardBrands();

        foreach ($availableBrands as $brand) {
            // Use lowercase brand as key for consistency
            $mapping[strtolower($brand)] = strtolower($brand);

            // Add variations for compatibility (capitalized, uppercase)
            $mapping[ucfirst(strtolower($brand))] = strtolower($brand);
            $mapping[strtoupper($brand)] = strtolower($brand);
        }

        // Add default fallback
        $mapping['default'] = 'default';

        return $mapping;
    }

    /**
     * Get available card brands from Monei API
     *
     * @return array
     */
    private function getAvailableCardBrands(): array
    {
        try {
            $paymentMethods = $this->getPaymentMethods->execute();

            // Access card brands through the metadata.card.brands property
            $cardBrands = [];
            $metadata = $paymentMethods->getMetadata();

            if ($metadata && $metadata->getCard() && $metadata->getCard()->getBrands()) {
                $cardBrands = $metadata->getCard()->getBrands();
            }

            $availableBrands = [];

            foreach ($cardBrands as $brand) {
                $availableBrands[] = strtolower($brand);
            }

            // Ensure we have some defaults if no brands were returned
            if (empty($availableBrands)) {
                $availableBrands = ['visa', 'mastercard', 'amex', 'discover', 'diners', 'jcb', 'unionpay', 'maestro'];
            }

            return array_unique($availableBrands);
        } catch (\Exception $e) {
            // Return default brands if API call fails
            return ['visa', 'mastercard', 'amex', 'discover', 'diners', 'jcb', 'unionpay', 'maestro'];
        }
    }

    /**
     * Get card icons for JavaScript configuration.
     *
     * @return array
     */
    private function getCardIcons(): array
    {
        $paymentMethodDetails = $this->paymentMethodHelper->getPaymentMethodDetails();
        $icons = [];

        // Get available brands from API
        $availableBrands = $this->getAvailableCardBrands();

        // Add card brand icons
        foreach ($availableBrands as $brand) {
            if (isset($paymentMethodDetails[$brand])) {
                $dimensions = $this->paymentMethodHelper->getPaymentMethodDimensions($brand);
                $icons[$brand] = [
                    'url' => $paymentMethodDetails[$brand]['icon'],
                    'width' => (int) str_replace('px', '', $dimensions['width']),
                    'height' => (int) str_replace('px', '', $dimensions['height']),
                    'title' => $paymentMethodDetails[$brand]['name']
                ];
            }
        }

        // Add default icon
        $dimensions = $this->paymentMethodHelper->getPaymentMethodDimensions('default');
        $icons['default'] = [
            'url' => $paymentMethodDetails['default']['icon'],
            'width' => (int) str_replace('px', '', $dimensions['width']),
            'height' => (int) str_replace('px', '', $dimensions['height']),
            'title' => __('Card')
        ];

        return $icons;
    }

    /**
     * Get payment method configuration with icon information
     *
     * @param string $methodCode
     * @param string|null $appendIcon
     * @return array
     */
    private function getMethodConfig(string $methodCode, ?string $appendIcon = null): array
    {
        $paymentConfig = [];
        $paymentActionUrl = $this->urlBuilder->getUrl('monei/action');
        $completeUrl = $this->urlBuilder->getUrl('monei/payment/complete');
        $cancelOrderUrl = $this->urlBuilder->getUrl('monei/payment/cancel');
        $failOrderStatus = Status::STATUS_FAILED;

        // Basic configuration
        $paymentConfig['completeUrl'] = $completeUrl;
        $paymentConfig['cancelOrderUrl'] = $cancelOrderUrl;
        $paymentConfig['failOrderStatus'] = $failOrderStatus;
        $paymentConfig['accountId'] = $this->moneiPaymentConfig->getApiKey();

        // Add payment icon
        $paymentType = str_replace('monei_', '', $methodCode);
        if ($appendIcon) {
            $paymentType = $appendIcon;
        }

        // Get icon URL
        $paymentConfig['icon'] = $this->paymentMethodHelper->getIconFromPaymentType($paymentType);

        // Get icon dimensions
        $dimensions = $this->paymentMethodHelper->getPaymentMethodDimensions($paymentType);
        $paymentConfig['iconWidth'] = (int) str_replace('px', '', $dimensions['width']);
        $paymentConfig['iconHeight'] = (int) str_replace('px', '', $dimensions['height']);

        return $paymentConfig;
    }

    /**
     * Get available payment methods list for frontend usage
     *
     * @param int|null $storeId
     * @return array
     */
    private function getAvailablePaymentMethodsList(?int $storeId = null): array
    {
        try {
            $paymentMethods = $this->getPaymentMethods->execute();

            return $paymentMethods->getPaymentMethods() ?? [];
        } catch (\Exception $e) {
            // Return empty array if API call fails
            return [];
        }
    }
}

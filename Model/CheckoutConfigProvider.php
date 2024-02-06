<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiGoogleApplePaymentModuleConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Block\Monei\Customer\CardRenderer;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Provides config data for payment.
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{

    public function __construct(
        private readonly UrlInterface                          $urlBuilder,
        private readonly MoneiPaymentModuleConfigInterface $moneiPaymentConfig,
        private readonly MoneiCardPaymentModuleConfigInterface $moneiCardPaymentConfig,
        private readonly MoneiGoogleApplePaymentModuleConfigInterface $moneiGoogleApplePaymentConfig,
        private readonly StoreManagerInterface                 $storeManager,
    )
    {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                Monei::CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'failOrderUrl' => $this->urlBuilder->getUrl('monei/payment/faillastorderbystatus'),
                    'failOrderStatus' => [
                        Monei::ORDER_STATUS_EXPIRED,
                        Monei::ORDER_STATUS_CANCELED,
                        Monei::ORDER_STATUS_FAILED,
                    ]
                ],
                Monei::CARD_CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'failOrderUrl' => $this->urlBuilder->getUrl('monei/payment/faillastorderbystatus'),
                    'failOrderStatus' => [
                        Monei::ORDER_STATUS_EXPIRED,
                        Monei::ORDER_STATUS_CANCELED,
                        Monei::ORDER_STATUS_FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($this->getStoreId()),
                    'isEnabledTokenization' => $this->moneiCardPaymentConfig->isEnabledTokenization($this->getStoreId()),
                    'ccVaultCode' => Monei::CC_VAULT_CODE,
                ],
                Monei::BIZUM_CODE => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'failOrderUrl' => $this->urlBuilder->getUrl('monei/payment/faillastorderbystatus'),
                    'failOrderStatus' => [
                        Monei::ORDER_STATUS_EXPIRED,
                        Monei::ORDER_STATUS_CANCELED,
                        Monei::ORDER_STATUS_FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($this->getStoreId())
                ],
                Monei::GOOGLE_APPLE_CODE => [
                    'googleTitle' => $this->moneiGoogleApplePaymentConfig->getGoogleTitle($this->getStoreId()),
                    'appleTitle' => $this->moneiGoogleApplePaymentConfig->getAppleTitle($this->getStoreId()),
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'failOrderUrl' => $this->urlBuilder->getUrl('monei/payment/faillastorderbystatus'),
                    'failOrderStatus' => [
                        Monei::ORDER_STATUS_EXPIRED,
                        Monei::ORDER_STATUS_CANCELED,
                        Monei::ORDER_STATUS_FAILED,
                    ],
                    'accountId' => $this->moneiPaymentConfig->getAccountId($this->getStoreId())
                ],
            ],
            'vault' => [
                Monei::CC_VAULT_CODE => [
                    'card_icons' => CardRenderer::ICON_TYPE_BY_BRAND,
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'failOrderUrl' => $this->urlBuilder->getUrl('monei/payment/faillastorderbystatus'),
                    'failOrderStatus' => [
                        Monei::ORDER_STATUS_EXPIRED,
                        Monei::ORDER_STATUS_CANCELED,
                        Monei::ORDER_STATUS_FAILED,
                    ],
                    'methodCardCode' => Monei::CARD_CODE
                ],
            ]
        ];
    }

    private function getStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException $e) {
            return 0;
        }
    }
}

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
use Magento\TestFramework\Store\StoreManager;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Provides config data for payment.
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{

    public function __construct(
        private readonly UrlInterface                      $urlBuilder,
        private readonly MoneiPaymentModuleConfigInterface $moneiPaymentConfig,
        private readonly StoreManagerInterface             $storeManager,
    )
    {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                'moneiMonei' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('monei/payment/redirect'),
                    'cancelOrderUrl' => $this->urlBuilder->getUrl('monei/payment/cancel'),
                    'failOrderUrl' => $this->urlBuilder->getUrl('monei/payment/faillastorderbystatus'),
                    'typeOfConnection' => $this->moneiPaymentConfig->getTypeOfConnection($this->getStoreId()),
                    'failOrderStatus' => [
                        Monei::ORDER_STATUS_EXPIRED,
                        Monei::ORDER_STATUS_CANCELED,
                        Monei::ORDER_STATUS_FAILED,
                    ],
                ],
            ],
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

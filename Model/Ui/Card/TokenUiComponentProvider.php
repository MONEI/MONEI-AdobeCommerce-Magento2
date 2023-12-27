<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Ui\Card;

use Magento\Framework\UrlInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{

    public function __construct(
        private readonly TokenUiComponentInterfaceFactory $componentFactory,
        private readonly UrlInterface                     $urlBuilder
    )
    {
    }

    /**
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => Monei::CC_VAULT,
                    'nonceUrl' => $this->getNonceRetrieveUrl(),
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Monei_MoneiPayment/js/view/payment/method-renderer/vault'
            ]
        );
    }

    /**
     * Get url to retrieve payment method nonce
     *
     * @return string
     */
    private function getNonceRetrieveUrl(): string
    {
        return $this->urlBuilder->getUrl(Monei::CODE . '/payment/getnonce', ['_secure' => true]);
    }
}

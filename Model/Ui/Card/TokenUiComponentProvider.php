<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Ui\Card;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Monei\MoneiPayment\Model\Payment\Monei;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    private TokenUiComponentInterfaceFactory $componentFactory;

    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory
    ) {
        $this->componentFactory = $componentFactory;
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
                    'code' => Monei::CC_VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
                ],
                'name' => 'Monei_MoneiPayment/js/view/payment/method-renderer/vault',
            ]
        );
    }
}

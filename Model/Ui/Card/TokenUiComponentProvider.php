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

/**
 * Provides UI components for saved card tokens in the Vault.
 *
 * This class creates UI components for displaying saved card information
 * from the payment vault during checkout.
 */
class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    /**
     * Factory for creating token UI components.
     *
     * @var TokenUiComponentInterfaceFactory
     */
    private TokenUiComponentInterfaceFactory $componentFactory;

    /**
     * Constructor for TokenUiComponentProvider.
     *
     * @param TokenUiComponentInterfaceFactory $componentFactory Factory for creating token UI components
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory
    ) {
        $this->componentFactory = $componentFactory;
    }

    /**
     * Get component for rendering saved token during checkout.
     *
     * Creates a UI component that displays saved card information to the customer
     * based on the token details stored in the vault.
     *
     * @param PaymentTokenInterface $paymentToken The saved payment token
     *
     * @return TokenUiComponentInterface Component used for token rendering
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

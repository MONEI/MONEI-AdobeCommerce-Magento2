<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Monei\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * @api
 *
 * This class extends AbstractCardRenderer which provides the following methods:
 * - getTokenDetails(): array - Returns the token details from the payment token
 * - getIconForType(string $type): array - Returns the icon data for the given card type
 */
class CardRenderer extends AbstractCardRenderer
{
    public const ICON_TYPE_BY_BRAND = [
        'visa' => 'VI',
        'mastercard' => 'MC',
        'diners' => 'DN',
        'amex' => 'AE',
        'jcb' => 'JCB',
        'unionpay' => 'UN',
    ];

    /**
     * Check if payment token can be rendered.
     *
     * @param PaymentTokenInterface $token
     *
     * @return bool
     */
    public function canRender(PaymentTokenInterface $token)
    {
        return Monei::CARD_CODE === $token->getPaymentMethodCode();
    }

    /**
     * Get last 4 digits of card.
     *
     * @return string
     */
    public function getNumberLast4Digits()
    {
        return $this->getTokenDetails()['last4'];
    }

    /**
     * Get card expiration date.
     *
     * @return string
     */
    public function getExpDate()
    {
        return $this->getTokenDetails()['expiration_date'];
    }

    /**
     * Get icon URL for card type.
     *
     * @return string
     */
    public function getIconUrl()
    {
        $brand = $this->getTokenDetails()['brand'];

        return $this->getIconForType($this->getPaymentIcon($brand))['url'];
    }

    /**
     * Get icon height for card type.
     *
     * @return int
     */
    public function getIconHeight()
    {
        $brand = $this->getTokenDetails()['brand'];

        return $this->getIconForType($this->getPaymentIcon($brand))['height'];
    }

    /**
     * Get icon width for card type.
     *
     * @return int
     */
    public function getIconWidth()
    {
        $brand = $this->getTokenDetails()['brand'];

        return $this->getIconForType($this->getPaymentIcon($brand))['width'];
    }

    /**
     * Get payment icon code by brand.
     *
     * @param string $brandCard
     */
    private function getPaymentIcon(string $brandCard): string
    {
        return self::ICON_TYPE_BY_BRAND[$brandCard] ?? $brandCard;
    }
}

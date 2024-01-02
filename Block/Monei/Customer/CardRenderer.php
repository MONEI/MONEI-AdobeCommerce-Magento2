<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Monei\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * @api
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

    public function canRender(PaymentTokenInterface $token)
    {
        return $token->getPaymentMethodCode() === Monei::CODE;
    }

    public function getNumberLast4Digits()
    {
        return $this->getTokenDetails()['last4'];
    }

    public function getExpDate()
    {
        return $this->getTokenDetails()['expiration_date'];
    }

    public function getIconUrl()
    {
        $brand = $this->getTokenDetails()['brand'];

        return $this->getIconForType($this->getPaymentIcon($brand))['url'];
    }


    public function getIconHeight()
    {
        $brand = $this->getTokenDetails()['brand'];

        return $this->getIconForType($this->getPaymentIcon($brand))['height'];
    }

    public function getIconWidth()
    {
        $brand = $this->getTokenDetails()['brand'];

        return $this->getIconForType($this->getPaymentIcon($brand))['width'];
    }

    private function getPaymentIcon(string $brandCard): string
    {
        return self::ICON_TYPE_BY_BRAND[$brandCard] ?? $brandCard;
    }
}

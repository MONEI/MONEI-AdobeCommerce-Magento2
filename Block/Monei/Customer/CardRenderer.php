<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Monei\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use Monei\MoneiPayment\Helper\PaymentMethod;
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
    /**
     * @var PaymentMethod
     */
    private $paymentMethodHelper;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param PaymentMethod $paymentMethodHelper
     * @param \Magento\Payment\Model\CcConfigProvider $iconsProvider
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        PaymentMethod $paymentMethodHelper,
        \Magento\Payment\Model\CcConfigProvider $iconsProvider,
        array $data = []
    ) {
        parent::__construct($context, $iconsProvider, $data);
        $this->paymentMethodHelper = $paymentMethodHelper;
    }

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
        $brand = $this->getTokenDetails()['brand'] ?? '';
        return $this->paymentMethodHelper->getIconFromPaymentType('card', $brand);
    }

    /**
     * Get icon height for card type.
     *
     * @return int
     */
    public function getIconHeight()
    {
        $brand = $this->getTokenDetails()['brand'] ?? '';
        $height = $this->paymentMethodHelper->getPaymentMethodHeight($brand);

        // Convert pixel string to int (e.g., '30px' to 30)
        if ($height) {
            return (int) str_replace('px', '', $height);
        }

        return 30;  // Default height if not found
    }

    /**
     * Get icon width for card type.
     *
     * @return int
     */
    public function getIconWidth()
    {
        $brand = $this->getTokenDetails()['brand'] ?? '';
        $width = $this->paymentMethodHelper->getPaymentMethodWidth($brand);

        // Convert pixel string to int (e.g., '40px' to 40)
        if ($width) {
            return (int) str_replace('px', '', $width);
        }

        return 40;  // Default width if not found
    }

    /**
     * Get card type or brand name.
     *
     * @return string
     */
    public function getCardType()
    {
        $brand = $this->getTokenDetails()['brand'] ?? '';

        // Try to get name from our payment method configuration
        $cardType = $this->paymentMethodHelper->getPaymentMethodName($brand);

        // If we don't have a specific name and get a generic one (based on formatting the code)
        // Try to make it look better
        if ($cardType === ucwords(str_replace('_', ' ', $brand))) {
            // Use title case instead of all uppercase for common card types
            if (strtolower($brand) === 'visa' || strtolower($brand) === 'mastercard') {
                $cardType = ucfirst(strtolower($brand));
            }
        }

        return $cardType ?: __('Card');
    }
}

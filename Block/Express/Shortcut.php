<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\Express;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Catalog\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Monei\MoneiPayment\Api\Config\MoneiExpressCheckoutConfigInterface;
use Monei\MoneiPayment\Api\Config\MoneiPaymentModuleConfigInterface;

/**
 * Express checkout shortcut button.
 */
class Shortcut extends Template implements ShortcutInterface
{
    /**
     * Alias Magento uses to identify this shortcut among others in the container.
     */
    public const ALIAS_ELEMENT_INDEX = 'monei_express_shortcut';

    /**
     * @var string
     */
    protected $_template = 'Monei_MoneiPayment::express/shortcut.phtml';

    /**
     * @var MoneiExpressCheckoutConfigInterface
     */
    private MoneiExpressCheckoutConfigInterface $expressConfig;

    /**
     * @var MoneiPaymentModuleConfigInterface
     */
    private MoneiPaymentModuleConfigInterface $moduleConfig;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var Data
     */
    private Data $catalogHelper;

    /**
     * @param Context                             $context
     * @param MoneiExpressCheckoutConfigInterface $expressConfig Express checkout configuration
     * @param MoneiPaymentModuleConfigInterface   $moduleConfig    Module configuration
     * @param Session                             $checkoutSession Checkout session
     * @param Data                                $catalogHelper   Gives the product on a product page
     * @param mixed[]                             $data
     */
    public function __construct(
        Context $context,
        MoneiExpressCheckoutConfigInterface $expressConfig,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Session $checkoutSession,
        Data $catalogHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->expressConfig = $expressConfig;
        $this->moduleConfig = $moduleConfig;
        $this->checkoutSession = $checkoutSession;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Alias for the shortcut container.
     */
    public function getAlias(): string
    {
        return self::ALIAS_ELEMENT_INDEX;
    }

    /**
     * Surface this button was mounted on.
     */
    public function getExpressLocation(): string
    {
        return (string) $this->getData('express_location');
    }

    /**
     * Configuration handed to the wallet component.
     *
     * The account id, never the API key: this is serialised into the page.
     *
     * @return mixed[]
     */
    public function getExpressConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $product = $this->isProductSurface() ? $this->catalogHelper->getProduct() : null;
        $cartAmount = (int) round((float) $quote->getBaseGrandTotal() * 100);
        // An empty quote reports itself as not virtual, so it must not count.
        $cartIsPhysical = $quote->getItemsCount() > 0 && !$quote->isVirtual();

        // The opening figure for the sheet, computed here. The client never
        // derives an amount from prices - on the product page it only scales the
        // product's unit price by the quantity typed, and adds the cart already
        // held, since the product joins that cart when the sheet opens.
        $config = [
            'accountId' => $this->moduleConfig->getAccountId(),
            'language' => $this->moduleConfig->getLanguage(),
            'location' => $this->getExpressLocation(),
            'style' => $this->expressConfig->getJsonStyle() ?: (object) [],
            'amount' => $cartAmount,
            // The store's base currency, not the quote's: a shopper who has not
            // added anything yet has a quote with no currency on it.
            'currency' => (string) $this->_storeManager->getStore()->getBaseCurrencyCode(),
            'requestShipping' => $cartIsPhysical,
            'paypal' => $this->expressConfig->isPayPalEnabled(),
        ];

        if ($product) {
            $unitAmount = (int) round((float) $product->getFinalPrice() * 100);
            $config['amount'] = $cartAmount + $unitAmount;
            $config['productAmount'] = $unitAmount;
            $config['requestShipping'] = $cartIsPhysical || !$product->isVirtual();
        }

        return $config;
    }

    /**
     * Whether this shortcut sits on a product page.
     */
    private function isProductSurface(): bool
    {
        return MoneiExpressCheckoutConfigInterface::LOCATION_PRODUCT === $this->getExpressLocation();
    }

    /**
     * Serialised config for the template.
     */
    public function getJsonExpressConfig(): string
    {
        return (string) json_encode($this->getExpressConfig());
    }
}

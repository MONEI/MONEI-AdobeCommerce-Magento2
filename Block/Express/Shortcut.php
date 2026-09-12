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
use Magento\Catalog\Helper\Data as CatalogHelper;
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
     * @var CatalogHelper
     */
    private CatalogHelper $catalogHelper;

    /**
     * @param Context                             $context
     * @param MoneiExpressCheckoutConfigInterface $expressConfig Express checkout configuration
     * @param MoneiPaymentModuleConfigInterface   $moduleConfig    Module configuration
     * @param Session                             $checkoutSession Checkout session
     * @param CatalogHelper                       $catalogHelper   Gives the product on a product page
     * @param mixed[]                             $data
     */
    public function __construct(
        Context $context,
        MoneiExpressCheckoutConfigInterface $expressConfig,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Session $checkoutSession,
        CatalogHelper $catalogHelper,
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

        // The opening figure for the sheet, computed here. The client never
        // derives an amount - it only ever names an address or an option. On the
        // product page the product is not in the cart yet, so its own price
        // opens the sheet; the server repaints the total once it is added.
        $amount = $product
            ? (float) $product->getFinalPrice()
            : (float) $quote->getBaseGrandTotal();

        return [
            'accountId' => $this->moduleConfig->getAccountId(),
            'language' => $this->moduleConfig->getLanguage(),
            'location' => $this->getExpressLocation(),
            'style' => $this->expressConfig->getJsonStyle() ?: (object) [],
            'amount' => (int) round($amount * 100),
            // The store's base currency, not the quote's: a shopper who has not
            // added anything yet has a quote with no currency on it.
            'currency' => (string) $this->_storeManager->getStore()->getBaseCurrencyCode(),
            'requestShipping' => $product ? !$product->isVirtual() || !$quote->isVirtual() : !$quote->isVirtual(),
            'paypal' => $this->expressConfig->isPayPalEnabled(),
        ];
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

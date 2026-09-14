<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Monei\MoneiPayment\Api\Config\MoneiExpressCheckoutConfigInterface;
use Monei\MoneiPayment\Block\Express\Shortcut;

/**
 * Adds the express checkout button to Magento's shortcut containers.
 *
 * One observer serves three surfaces. Magento distinguishes them with two flags on
 * the event: a catalog product page, the cart page, or neither - which is the mini
 * cart.
 */
class AddExpressButton implements ObserverInterface
{
    /**
     * Magento_Msrp's "click for price" popup container. The popup renders its children
     * inside a <script type="text/x-magento-template">, so the shortcut's own inline
     * <script> would close that tag early and break the rest of the page. It also has
     * no product amount to charge.
     */
    private const MSRP_POPUP_CONTAINER = 'map.shortcut.buttons';

    /**
     * @var MoneiExpressCheckoutConfigInterface
     */
    private MoneiExpressCheckoutConfigInterface $config;

    /**
     * @param MoneiExpressCheckoutConfigInterface $config Express checkout configuration
     */
    public function __construct(MoneiExpressCheckoutConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Add the express shortcut button when the surface is enabled.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $event = $observer->getEvent();

        /** @var \Magento\Catalog\Block\ShortcutButtons $shortcutButtons */
        $shortcutButtons = $event->getContainer();

        if ($shortcutButtons->getNameInLayout() === self::MSRP_POPUP_CONTAINER) {
            return;
        }

        $location = $this->resolveLocation(
            (bool) $event->getIsCatalogProduct(),
            (bool) $event->getIsShoppingCart()
        );

        if (!$this->config->isEnabledAt($location)) {
            return;
        }

        /** @var Shortcut $shortcut */
        $shortcut = $shortcutButtons->getLayout()->createBlock(Shortcut::class);
        $shortcut->setExpressLocation($location);

        $shortcutButtons->addShortcut($shortcut);
    }

    /**
     * Work out which surface the event came from.
     *
     * @param bool $isCatalogProduct
     * @param bool $isShoppingCart
     */
    private function resolveLocation(bool $isCatalogProduct, bool $isShoppingCart): string
    {
        if ($isCatalogProduct) {
            return MoneiExpressCheckoutConfigInterface::LOCATION_PRODUCT;
        }

        if ($isShoppingCart) {
            return MoneiExpressCheckoutConfigInterface::LOCATION_CART;
        }

        return MoneiExpressCheckoutConfigInterface::LOCATION_MINICART;
    }
}

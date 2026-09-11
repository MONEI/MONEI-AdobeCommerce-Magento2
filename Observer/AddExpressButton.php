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
        $location = $this->resolveLocation(
            (bool) $event->getIsCatalogProduct(),
            (bool) $event->getIsShoppingCart()
        );

        // The product page has no quote for the displayed product yet: the checkout
        // session total is the existing cart, not the intended purchase. Until the
        // product-page quote endpoints exist, mounting here would open the wallet
        // with the wrong amount, so the surface is withheld regardless of config.
        if (MoneiExpressCheckoutConfigInterface::LOCATION_PRODUCT === $location) {
            return;
        }

        if (!$this->config->isEnabledAt($location)) {
            return;
        }

        /** @var \Magento\Catalog\Block\ShortcutButtons $shortcutButtons */
        $shortcutButtons = $event->getContainer();

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

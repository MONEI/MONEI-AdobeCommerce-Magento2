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
     * @param Context                             $context
     * @param MoneiExpressCheckoutConfigInterface $expressConfig Express checkout configuration
     * @param MoneiPaymentModuleConfigInterface   $moduleConfig    Module configuration
     * @param Session                             $checkoutSession Checkout session
     * @param mixed[]                             $data
     */
    public function __construct(
        Context $context,
        MoneiExpressCheckoutConfigInterface $expressConfig,
        MoneiPaymentModuleConfigInterface $moduleConfig,
        Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->expressConfig = $expressConfig;
        $this->moduleConfig = $moduleConfig;
        $this->checkoutSession = $checkoutSession;
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

        return [
            'accountId' => $this->moduleConfig->getAccountId(),
            'language' => $this->moduleConfig->getLanguage(),
            'location' => $this->getExpressLocation(),
            'style' => $this->expressConfig->getJsonStyle() ?: (object) [],
            // The opening figure for the sheet, computed here. The client never
            // derives an amount - it only ever names an address or an option.
            'amount' => (int) round((float) $quote->getBaseGrandTotal() * 100),
            'currency' => (string) $quote->getBaseCurrencyCode(),
            'requestShipping' => !$quote->isVirtual(),
        ];
    }

    /**
     * Serialised config for the template.
     */
    public function getJsonExpressConfig(): string
    {
        return (string) json_encode($this->getExpressConfig());
    }
}

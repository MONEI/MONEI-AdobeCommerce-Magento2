<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\System\Config\Form\Field;

use Magento\Backend\Block\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;

class ApplePayNotice extends Notice
{
    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        array $data = []
    ) {
        $this->escaper = $escaper;
        parent::__construct($context, $data);
    }

    /**
     * Render element html with explicit translation handling.
     *
     * @param AbstractElement $element
     */
    public function render(AbstractElement $element): string
    {
        // Define the translation key and explicitly translate it
        $linkText = __('here');
        $message = __(
            'You must register your domain in the "MONEI Dashboard" to use Apple Pay. For more information, click %1.',
            '<a href="https://dashboard.monei.com/settings/payment-methods" target="_blank" rel="noopener noreferrer">' . $this->escaper->escapeHtml($linkText) . '</a>'
        );

        // Replace the comment with our explicitly translated version
        $element->setComment($message);

        // Call parent render method
        return parent::render($element);
    }
}

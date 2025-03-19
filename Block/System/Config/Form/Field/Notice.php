<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\System\Config\Form\Field;

use Magento\Backend\Block\AbstractBlock;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Notice extends AbstractBlock implements RendererInterface
{
    /**
     * Render element html.
     *
     * @param AbstractElement $element
     */
    public function render(AbstractElement $element): string
    {
        return \sprintf(
            '<tr class="notice" id="row_%s">'
                . '<td class="label"><label for="%s">%s</label></td>'
                . '<td class="value"><div class="message message-warning">%s</div></td>'
                . '</tr>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $element->getLabel(),
            $element->getComment(),
        );
    }
}

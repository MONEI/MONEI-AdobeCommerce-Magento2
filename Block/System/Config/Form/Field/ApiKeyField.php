<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * An API key field that never puts a scoped key (mk_) in the page, whatever its source (database, env.php or an
 * environment variable): the field is empty with "•••• <last 4>" as placeholder. An empty submit keeps the key
 * (ApiKey).
 */
class ApiKeyField extends Field
{
    /**
     * Render the input without a scoped key
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $key = (string) $element->getValue();
        if (strpos($key, 'mk_') === 0) {
            $element->setValue('');
            $element->setPlaceholder('•••• ' . substr($key, -4));
            $element->setComment(__('Set by Connect with MONEI. Leave empty to keep this key.'));
        }

        return (string) preg_replace('/<input /', '<input autocomplete="new-password" ', parent::_getElementHtml($element), 1);
    }
}

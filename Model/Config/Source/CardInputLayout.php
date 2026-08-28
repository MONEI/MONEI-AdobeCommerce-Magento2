<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Monei\MoneiPayment\Api\Config\MoneiCardPaymentModuleConfigInterface;

/**
 * Card input layout source model.
 */
class CardInputLayout implements OptionSourceInterface
{
    /**
     * Get array of options for the card input layout configuration.
     *
     * @return array Array of options: [['label' => string, 'value' => string]]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'label' => __('Separate fields (card number, expiry, CVC)'),
                'value' => MoneiCardPaymentModuleConfigInterface::LAYOUT_SPLIT,
            ],
            [
                'label' => __('Single combined field'),
                'value' => MoneiCardPaymentModuleConfigInterface::LAYOUT_SINGLE,
            ],
        ];
    }
}

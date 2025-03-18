<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Monei type of payment source model.
 */
class TypeOfPayment implements OptionSourceInterface
{
    public const TYPE_PRE_AUTHORIZED = 1;

    public const TYPE_AUTHORIZED = 2;

    /**
     * Get array of options for payment type configuration.
     *
     * @return array Array of options: [['label' => string, 'value' => int]]
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => __('Authorize'), 'value' => self::TYPE_PRE_AUTHORIZED],
            ['label' => __('Authorize and Capture'), 'value' => self::TYPE_AUTHORIZED],
        ];
    }
}

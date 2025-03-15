<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Monei connection type source model.
 */
class TypeOfConnection implements OptionSourceInterface
{
    public const TYPE_REDIRECT = 'redirect';
    public const TYPE_INSITE = 'insite';

    /**
     * Get array of options for connection type configuration.
     *
     * @return array Array of options: [['label' => string, 'value' => string]]
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => __('Redirect'), 'value' => self::TYPE_REDIRECT],
            ['label' => __('Insite'), 'value' => self::TYPE_INSITE],
        ];
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Monei payment mode source model.
 */
class Mode implements OptionSourceInterface
{
    public const MODE_TEST = 1;

    public const MODE_PRODUCTION = 2;

    /**
     * Get array of options for payment mode configuration
     *
     * @return array Array of options: [['label' => string, 'value' => int]]
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => __('Test'), 'value' => self::MODE_TEST],
            ['label' => __('Production'), 'value' => self::MODE_PRODUCTION],
        ];
    }
}

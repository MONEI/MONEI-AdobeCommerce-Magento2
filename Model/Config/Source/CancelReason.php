<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Monei preauthorized order status source model.
 */
class CancelReason implements OptionSourceInterface
{
    /**
     * Get options for cancel reason dropdown
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => __('Duplicated'), 'value' => 'duplicated'],
            ['label' => __('Fraudulent'), 'value' => 'fraudulent'],
            ['label' => __('Requested by customer'), 'value' => 'requested_by_customer'],
        ];
    }
}

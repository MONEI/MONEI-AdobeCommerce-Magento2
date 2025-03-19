<?php declare(strict_types=1);

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Order Statuses source model for Processing state.
 */
class ProcessingStatus extends Status
{
    /** @var string */
    protected $_stateStatuses = Order::STATE_PROCESSING;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = parent::toOptionArray();

        // Add Monei-specific status if not already present
        $moneiSucceededFound = false;
        foreach ($options as $option) {
            if (isset($option['value']) && $option['value'] === Monei::STATUS_MONEI_SUCCEEDED) {
                $moneiSucceededFound = true;

                break;
            }
        }

        if (!$moneiSucceededFound) {
            $options[] = [
                'value' => Monei::STATUS_MONEI_SUCCEEDED,
                'label' => __('MONEI - Succeeded')
            ];
        }

        // Filter out unwanted statuses
        return array_filter($options, function ($option) {
            // Skip if array doesn't have value or label
            if (!isset($option['value']) || !isset($option['label'])) {
                return true;
            }

            $label = strtolower((string) $option['label']);

            // Exclude statuses containing "partially refunded" or "fraud"
            return (strpos($label, 'partially refunded') === false &&
                strpos($label, 'fraud') === false);
        });
    }
}

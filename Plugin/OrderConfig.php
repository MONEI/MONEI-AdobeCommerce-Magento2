<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Model\Order\Config;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Plugin to ensure MONEI custom order statuses are visible in the customer account
 */
class OrderConfig
{
    /**
     * Add MONEI custom statuses to the list of statuses visible on frontend
     *
     * @param Config $subject
     * @param array $result
     * @return array
     */
    public function afterGetVisibleOnFrontStatuses(Config $subject, array $result): array
    {
        // Add all MONEI custom statuses to the visible on front statuses
        return array_merge($result, [
            Monei::STATUS_MONEI_PENDING,
            Monei::STATUS_MONEI_AUTHORIZED,
            Monei::STATUS_MONEI_EXPIRED,
            Monei::STATUS_MONEI_FAILED,
            Monei::STATUS_MONEI_SUCCEEDED,
            Monei::STATUS_MONEI_PARTIALLY_REFUNDED,
            Monei::STATUS_MONEI_REFUNDED
        ]);
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Sales\Model\Order\StatusLabel;
use Monei\MoneiPayment\Model\Payment\Monei;
use Magento\Sales\Model\Order;

/**
 * Plugin to map MONEI custom order statuses to standard Magento labels in the customer account
 */
class OrderStatusLabel
{
    /**
     * Map of MONEI status codes to standard Magento status labels
     * Maps MONEI-specific statuses to standard Magento status equivalents for better frontend readability
     *
     * @var array
     */
    private array $statusMappings = [
        Monei::STATUS_MONEI_PENDING => 'Pending',
        Monei::STATUS_MONEI_AUTHORIZED => 'Pending Payment',
        Monei::STATUS_MONEI_EXPIRED => 'Canceled',
        Monei::STATUS_MONEI_FAILED => 'Canceled',
        Monei::STATUS_MONEI_SUCCEEDED => 'Processing',
        Monei::STATUS_MONEI_PARTIALLY_REFUNDED => 'Processing',
        Monei::STATUS_MONEI_REFUNDED => 'Closed'
    ];

    /**
     * After plugin to map MONEI custom status codes to standard Magento status labels
     *
     * @param StatusLabel $subject
     * @param string|null $result
     * @param string|null $status
     * @return string|null
     */
    public function afterGetStatusLabel(StatusLabel $subject, ?string $result, ?string $status): ?string
    {
        if ($status && isset($this->statusMappings[$status])) {
            return (string)__($this->statusMappings[$status]);
        }

        return $result;
    }
}

<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Model\Order\StatusLabel;
use Monei\MoneiPayment\Model\Payment\Monei;

/**
 * Plugin to map MONEI custom order statuses to standard Magento labels in the customer account
 */
class SalesOrderStatusLabel
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
     * @var State
     */
    private $appState;

    /**
     * @param State $appState
     */
    public function __construct(
        State $appState
    ) {
        $this->appState = $appState;
    }

    /**
     * After plugin to map MONEI custom status codes to standard Magento status labels
     * Only applies mapping in frontend area, preserves original MONEI labels in admin
     *
     * @param StatusLabel $subject
     * @param string|null $result
     * @param string|null $status
     * @return string|null
     */
    public function afterGetStatusLabel(StatusLabel $subject, ?string $result, ?string $status): ?string
    {
        try {
            // Only apply the mapping in the frontend area
            // In admin area, use the default status labels from the database
            if ($this->appState->getAreaCode() !== Area::AREA_ADMINHTML && $status && isset($this->statusMappings[$status])) {
                return (string) __($this->statusMappings[$status]);
            }
        } catch (\Exception $e) {
            // If we can't determine the area code, continue with default behavior
            return $result;
        }

        return $result;
    }
}

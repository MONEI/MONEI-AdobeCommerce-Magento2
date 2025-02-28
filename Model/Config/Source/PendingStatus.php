<?php

declare(strict_types=1);

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

namespace Monei\MoneiPayment\Model\Config\Source;

use Magento\Sales\Model\Config\Source\Order\Status;

/**
 * Order Statuses source model
 */
class PendingStatus extends Status
{
    /**
     * @var string
     */
    protected $_stateStatuses = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
}

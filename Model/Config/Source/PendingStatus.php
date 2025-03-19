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

/**
 * Order Statuses source model.
 */
class PendingStatus extends Status
{
    /** @var string */
    protected $_stateStatuses = Order::STATE_PENDING_PAYMENT;
}

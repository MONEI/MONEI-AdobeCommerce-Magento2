<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model;

use Magento\Framework\Model\AbstractModel;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;

/**
 * Model for pending Monei orders.
 */
class PendingOrder extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PendingOrderResource::class);
    }
}

<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\ResourceModel\PendingOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Monei\MoneiPayment\Model\PendingOrder;
use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;

/**
 * Pending order collection class.
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(PendingOrder::class, PendingOrderResource::class);
    }
}

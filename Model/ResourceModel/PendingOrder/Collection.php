<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\ResourceModel\PendingOrder;

use Monei\MoneiPayment\Model\ResourceModel\PendingOrder as PendingOrderResource;
use Monei\MoneiPayment\Model\PendingOrder;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Pending order collection class.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(PendingOrder::class, PendingOrderResource::class);
    }
}

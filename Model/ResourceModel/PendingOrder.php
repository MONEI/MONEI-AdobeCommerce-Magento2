<?php

/**
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * PendingOrder resourceModel class.
 */
class PendingOrder extends AbstractDb
{
    public const TABLE_NAME = 'pending_monei_orders';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }
}

<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Maps a Magento customer to a MONEI customer per account ID and mode
 */
class MoneiCustomer extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('monei_customer', 'entity_id');
    }

    /**
     * Get the MONEI customer ID for a customer, account ID and mode.
     *
     * @param int $customerId
     * @param string $accountId
     * @param string $mode
     * @return string|null
     */
    public function getMoneiCustomerId(int $customerId, string $accountId, string $mode): ?string
    {
        $connection = $this->getConnection();
        $select = $connection
            ->select()
            ->from($this->getMainTable(), ['monei_customer_id'])
            ->where('customer_id = ?', $customerId)
            ->where('account_id = ?', $accountId)
            ->where('mode = ?', $mode);
        $moneiCustomerId = $connection->fetchOne($select);

        return $moneiCustomerId === false ? null : (string) $moneiCustomerId;
    }

    /**
     * Insert a mapping row.
     *
     * @param int $customerId
     * @param string $accountId
     * @param string $mode
     * @param string $moneiCustomerId
     * @return void
     * @throws \Magento\Framework\DB\Adapter\DuplicateException When a row for the same scope exists
     */
    public function saveRow(int $customerId, string $accountId, string $mode, string $moneiCustomerId): void
    {
        $this->getConnection()->insert(
            $this->getMainTable(),
            [
                'customer_id' => $customerId,
                'account_id' => $accountId,
                'mode' => $mode,
                'monei_customer_id' => $moneiCustomerId,
            ]
        );
    }

    /**
     * Delete the mapping row for a customer, account ID and mode, only while it holds the given MONEI customer ID.
     *
     * @param int $customerId
     * @param string $accountId
     * @param string $mode
     * @param string $moneiCustomerId
     * @return void
     */
    public function deleteRow(int $customerId, string $accountId, string $mode, string $moneiCustomerId): void
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            [
                'customer_id = ?' => $customerId,
                'account_id = ?' => $accountId,
                'mode = ?' => $mode,
                'monei_customer_id = ?' => $moneiCustomerId,
            ]
        );
    }

    /**
     * Get all mapping rows of a customer.
     *
     * @param int $customerId
     * @return array
     */
    public function getRowsByCustomer(int $customerId): array
    {
        $connection = $this->getConnection();
        $select = $connection
            ->select()
            ->from($this->getMainTable())
            ->where('customer_id = ?', $customerId);

        return $connection->fetchAll($select);
    }
}

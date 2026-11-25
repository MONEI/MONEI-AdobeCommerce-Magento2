<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Magento\Framework\Flag\FlagResource;
use Magento\Framework\FlagManager;

/**
 * The flag table entries of "Connect with MONEI": the connect states, the last connect result and the fetch quota.
 *
 * The flag table is persistent storage, never a cache, so a state survives a cache flush.
 */
class FlagStore
{
    /**
     * @var FlagManager
     */
    private FlagManager $flagManager;

    /**
     * @var FlagResource
     */
    private FlagResource $flagResource;

    /**
     * @param FlagManager $flagManager
     * @param FlagResource $flagResource
     */
    public function __construct(FlagManager $flagManager, FlagResource $flagResource)
    {
        $this->flagManager = $flagManager;
        $this->flagResource = $flagResource;
    }

    /**
     * Get the data of a flag, or null
     *
     * @param string $code
     * @return mixed
     */
    public function get(string $code)
    {
        return $this->flagManager->getFlagData($code);
    }

    /**
     * Save the data of a flag
     *
     * @param string $code
     * @param array $value
     * @return void
     */
    public function set(string $code, array $value): void
    {
        $this->flagManager->saveFlag($code, $value);
    }

    /**
     * Delete a flag
     *
     * @param string $code
     * @return bool True only when this call removed the flag: of two callbacks with one state, one wins.
     */
    public function delete(string $code): bool
    {
        return $this->flagResource->getConnection()->delete(
            $this->flagResource->getMainTable(),
            ['flag_code = ?' => $code]
        ) > 0;
    }

    /**
     * Get the codes of the flags that start with a prefix
     *
     * @param string $prefix
     * @return string[]
     */
    public function codesWithPrefix(string $prefix): array
    {
        $connection = $this->flagResource->getConnection();
        $select = $connection->select()
            ->from($this->flagResource->getMainTable(), ['flag_code'])
            ->where('flag_code LIKE ?', addcslashes($prefix, '%_\\') . '%');

        return $connection->fetchCol($select);
    }
}

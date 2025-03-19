<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Registry;

class AccountId
{
    /**
     * The Monei account identifier.
     *
     * @var string|null
     */
    private ?string $accountId = null;

    /**
     * Set account ID.
     *
     * @param string $accountId
     */
    public function set(string $accountId): void
    {
        $this->accountId = $accountId;
    }

    /**
     * Get account ID.
     */
    public function get(): ?string
    {
        return $this->accountId;
    }
}

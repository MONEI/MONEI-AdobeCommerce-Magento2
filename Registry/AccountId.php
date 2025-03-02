<?php

/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Registry;

class AccountId
{
    private ?string $accountId = null;

    /**
     * Set account ID.
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

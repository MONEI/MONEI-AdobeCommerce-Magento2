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

    public function set(string $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function get(): ?string
    {
        return $this->accountId;
    }
}

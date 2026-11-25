<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Service\Connect;

use Monei\MoneiPayment\Service\Connect\FlagStore;

/**
 * The flag table in memory.
 */
class MemoryFlagStore extends FlagStore
{
    /**
     * @var array<string, array>
     */
    public array $flags;

    /**
     * Codes whose delete another request wins: the flag goes, but this delete reports false.
     *
     * @var string[]
     */
    public array $lostDeletes = [];

    /**
     * @param array<string, array> $flags
     */
    public function __construct(array $flags = [])
    {
        $this->flags = $flags;
    }

    /**
     * @inheritDoc
     */
    public function get(string $code)
    {
        return $this->flags[$code] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function set(string $code, array $value): void
    {
        $this->flags[$code] = $value;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $code): bool
    {
        $existed = array_key_exists($code, $this->flags);
        unset($this->flags[$code]);

        return $existed && !in_array($code, $this->lostDeletes, true);
    }

    /**
     * @inheritDoc
     */
    public function codesWithPrefix(string $prefix): array
    {
        return array_values(array_filter(array_keys($this->flags), function ($code) use ($prefix) {
            return strpos($code, $prefix) === 0;
        }));
    }
}

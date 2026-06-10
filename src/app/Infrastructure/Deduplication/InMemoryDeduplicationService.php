<?php

declare(strict_types=1);

namespace App\Infrastructure\Deduplication;

/**
 * Array-based deduplication for testing — no Redis dependency.
 */
class InMemoryDeduplicationService implements DeduplicationServiceInterface
{
    /** @var array<string, true> */
    private array $locks = [];

    public function acquireLock(string $key, ?int $ttlSeconds = null): bool
    {
        if (isset($this->locks[$key])) {
            return false;
        }
        $this->locks[$key] = true;
        return true;
    }

    public function releaseLock(string $key): void
    {
        unset($this->locks[$key]);
    }

    public function isLocked(string $key): bool
    {
        return isset($this->locks[$key]);
    }
}

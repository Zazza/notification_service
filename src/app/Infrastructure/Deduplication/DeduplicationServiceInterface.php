<?php

declare(strict_types=1);

namespace App\Infrastructure\Deduplication;

interface DeduplicationServiceInterface
{
    /**
     * Try to acquire an idempotency lock. Returns true if acquired.
     */
    public function acquireLock(string $key, ?int $ttlSeconds = null): bool;

    /**
     * Release an idempotency lock.
     */
    public function releaseLock(string $key): void;

    /**
     * Check if a key is already locked (processing).
     */
    public function isLocked(string $key): bool;
}

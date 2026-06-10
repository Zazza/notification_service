<?php

declare(strict_types=1);

namespace App\Infrastructure\Deduplication;

use Redis;

class RedisDeduplicationService implements DeduplicationServiceInterface
{
    public function __construct(
        private readonly Redis $redis,
    ) {
    }

    public function acquireLock(string $key, ?int $ttlSeconds = null): bool
    {
        $redisKey = 'dedup:' . $key;
        $ttlSeconds ??= (int) config('notification.lock_ttl');
        $result = $this->redis->set($redisKey, '1', ['NX', 'EX' => $ttlSeconds]);

        return $result === true;
    }

    public function releaseLock(string $key): void
    {
        $this->redis->del('dedup:' . $key);
    }

    public function isLocked(string $key): bool
    {
        return (bool) $this->redis->exists('dedup:' . $key);
    }
}

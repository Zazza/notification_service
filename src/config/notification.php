<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Publisher Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "rabbitmq", "sync"
    |
    | "sync" sends notifications synchronously (useful for testing).
    | "rabbitmq" publishes to RabbitMQ queue.
    |
    */

    'publisher' => env('NOTIFICATION_PUBLISHER', 'rabbitmq'),

    /*
    |--------------------------------------------------------------------------
    | Deduplication Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "redis", "memory"
    |
    | "redis" uses Redis for deduplication storage.
    | "memory" uses an in-memory store (useful for testing).
    |
    */

    'deduplication_driver' => env('DEDUPLICATION_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Max Retry Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of times a notification will be retried before
    | being discarded. Defaults to 3.
    |
    */

    'max_retries' => env('NOTIFICATION_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Lock TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Time-to-live for idempotency and deduplication locks.
    | Prevents the same notification from being processed twice.
    |
    */

    'lock_ttl' => env('NOTIFICATION_LOCK_TTL', 300),

];

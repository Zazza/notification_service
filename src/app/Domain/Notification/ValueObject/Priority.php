<?php

declare(strict_types=1);

namespace App\Domain\Notification\ValueObject;

enum Priority: string
{
    case HIGH = 'high';
    case NORMAL = 'normal';
    case LOW = 'low';

    public function getQueueName(): string
    {
        return match ($this) {
            self::HIGH => 'notification.high',
            self::NORMAL => 'notification.default',
            self::LOW => 'notification.low',
        };
    }

    public function getWeight(): int
    {
        return match ($this) {
            self::HIGH => 100,
            self::NORMAL => 50,
            self::LOW => 10,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notification\ValueObject;

enum NotificationStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case DISCARDED = 'discarded';

    public function getLabel(): string
    {
        return match ($this) {
            self::QUEUED => 'В очереди',
            self::SENT => 'Отправлено',
            self::DELIVERED => 'Доставлено',
            self::DISCARDED => 'Отброшено',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::DISCARDED], true);
    }
}

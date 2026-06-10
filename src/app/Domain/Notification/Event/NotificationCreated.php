<?php

declare(strict_types=1);

namespace App\Domain\Notification\Event;

use App\Domain\Notification\ValueObject\NotificationId;

final readonly class NotificationCreated
{
    public function __construct(
        public NotificationId $notificationId,
    ) {
    }
}

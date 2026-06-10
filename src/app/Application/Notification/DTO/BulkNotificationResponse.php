<?php

declare(strict_types=1);

namespace App\Application\Notification\DTO;

final readonly class BulkNotificationResponse
{
    /**
     * @param NotificationDTO[] $notifications
     */
    public function __construct(
        public int $total,
        public array $notifications,
    ) {
    }
}

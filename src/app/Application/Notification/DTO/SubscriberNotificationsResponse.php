<?php

declare(strict_types=1);

namespace App\Application\Notification\DTO;

final readonly class SubscriberNotificationsResponse
{
    /**
     * @param NotificationDTO[] $notifications
     */
    public function __construct(
        public string $recipientId,
        public int $total,
        public array $notifications,
    ) {
    }
}

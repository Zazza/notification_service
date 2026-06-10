<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Notification\Entity\Notification;

/**
 * Synchronous publisher for testing — does not actually send to RabbitMQ.
 */
class SyncNotificationPublisher implements NotificationPublisherInterface
{
    /** @var Notification[] */
    private array $published = [];

    public function publish(Notification $notification): void
    {
        $this->published[] = $notification;
    }

    /**
     * @return Notification[]
     */
    public function getPublished(): array
    {
        return $this->published;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Notification\Entity\Notification;

interface NotificationPublisherInterface
{
    public function publish(Notification $notification): void;
}

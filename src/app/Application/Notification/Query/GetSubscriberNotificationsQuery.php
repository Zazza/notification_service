<?php

declare(strict_types=1);

namespace App\Application\Notification\Query;

final readonly class GetSubscriberNotificationsQuery
{
    public function __construct(
        public string $recipientId,
        public ?string $status = null,
    ) {
    }
}

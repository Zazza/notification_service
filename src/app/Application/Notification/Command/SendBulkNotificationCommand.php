<?php

declare(strict_types=1);

namespace App\Application\Notification\Command;

final readonly class SendBulkNotificationCommand
{
    /**
     * @param string $channel
     * @param string $priority
     * @param string[] $recipientIds
     * @param string $content
     */
    public function __construct(
        public string $channel,
        public string $priority,
        public array $recipientIds,
        public string $content,
    ) {
    }
}

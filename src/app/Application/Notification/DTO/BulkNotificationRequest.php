<?php

declare(strict_types=1);

namespace App\Application\Notification\DTO;

final readonly class BulkNotificationRequest
{
    /**
     * @param string $channel 'sms' | 'email'
     * @param string $priority 'high' | 'normal' | 'low'
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

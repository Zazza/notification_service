<?php

declare(strict_types=1);

namespace App\Application\Notification\DTO;

final readonly class NotificationDTO
{
    public function __construct(
        public string $id,
        public string $channel,
        public string $priority,
        public string $status,
        public string $recipientId,
        public string $content,
        public ?string $errorMessage,
        public ?string $gatewayId,
        public int $retryCount,
        public ?string $sentAt,
        public ?string $deliveredAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}

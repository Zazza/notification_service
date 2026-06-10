<?php

declare(strict_types=1);

namespace App\Domain\Notification\Service;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Exception\DuplicateNotificationException;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\Channel;
use App\Domain\Notification\ValueObject\Priority;

readonly class NotificationDomainService
{
    public function __construct(
        private NotificationRepositoryInterface $repository,
    ) {
    }

    /**
     * @param string[] $recipientIds
     * @return Notification[]
     */
    public function createBulkNotifications(
        Channel $channel,
        Priority $priority,
        array $recipientIds,
        string $content,
    ): array {
        $notifications = [];

        foreach ($recipientIds as $recipientId) {
            $idempotencyKey = $this->generateIdempotencyKey($channel, $recipientId, $content);

            if ($this->repository->existsByIdempotencyKey($idempotencyKey)) {
                throw DuplicateNotificationException::withKey($idempotencyKey);
            }

            $notifications[] = Notification::create(
                $channel,
                $priority,
                $recipientId,
                $content,
                $idempotencyKey,
            );
        }

        foreach ($notifications as $notification) {
            $this->repository->save($notification);
        }

        return $notifications;
    }

    private function generateIdempotencyKey(Channel $channel, string $recipientId, string $content): string
    {
        return md5($channel->value . ':' . $recipientId . ':' . $content);
    }
}

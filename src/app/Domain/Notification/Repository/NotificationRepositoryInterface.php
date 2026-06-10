<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repository;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\Notification\ValueObject\NotificationStatus;

interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;

    public function findById(NotificationId $id): ?Notification;

    /**
     * @return Notification[]
     */
    public function findByRecipientId(string $recipientId): array;

    /**
     * @return Notification[]
     */
    public function findByRecipientIdAndStatus(string $recipientId, NotificationStatus $status): array;

    public function existsByIdempotencyKey(string $idempotencyKey): bool;

    /**
     * Atomically finds and locks a notification for processing.
     * Used for exactly-once delivery semantics.
     */
    public function findByIdForUpdate(NotificationId $id): ?Notification;
}

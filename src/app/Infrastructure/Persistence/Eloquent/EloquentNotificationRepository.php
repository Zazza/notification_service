<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\Channel;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\Notification\ValueObject\NotificationStatus;
use App\Domain\Notification\ValueObject\Priority;

readonly class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private NotificationModel $model,
    ) {
    }

    public function save(Notification $notification): void
    {
        $data = [
            'id' => $notification->getId()->getValue(),
            'channel' => $notification->getChannel()->value,
            'priority' => $notification->getPriority()->value,
            'status' => $notification->getStatus()->value,
            'recipient_id' => $notification->getRecipientId(),
            'content' => $notification->getContent(),
            'idempotency_key' => $notification->getIdempotencyKey(),
            'gateway_id' => $notification->getGatewayId(),
            'error_message' => $notification->getErrorMessage(),
            'retry_count' => 0,
            'sent_at' => $notification->getSentAt(),
            'delivered_at' => $notification->getDeliveredAt(),
        ];

        $this->model->newQuery()->updateOrCreate(
            ['id' => $notification->getId()->getValue()],
            $data,
        );
    }

    public function findById(NotificationId $id): ?Notification
    {
        $row = $this->model->newQuery()->find($id->getValue());

        if ($row === null) {
            return null;
        }

        return $this->toEntity($row);
    }

    /**
     * @return Notification[]
     */
    public function findByRecipientId(string $recipientId): array
    {
        /** @var NotificationModel[] $rows */
        $rows = $this->model->newQuery()
            ->where('recipient_id', $recipientId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();

        return array_map(fn(NotificationModel $row) => $this->toEntity($row), $rows);
    }

    /**
     * @return Notification[]
     */
    public function findByRecipientIdAndStatus(string $recipientId, NotificationStatus $status): array
    {
        /** @var NotificationModel[] $rows */
        $rows = $this->model->newQuery()
            ->where('recipient_id', $recipientId)
            ->where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();

        return array_map(fn(NotificationModel $row) => $this->toEntity($row), $rows);
    }

    public function existsByIdempotencyKey(string $idempotencyKey): bool
    {
        return $this->model->newQuery()
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    public function findByIdForUpdate(NotificationId $id): ?Notification
    {
        /** @var NotificationModel|null $row */
        $row = $this->model->newQuery()
            ->where('id', $id->getValue())
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toEntity($row);
    }

    private function toEntity(NotificationModel $row): Notification
    {
        return Notification::reconstitute(
            id: NotificationId::fromString($row->id),
            channel: Channel::from($row->channel),
            priority: Priority::from($row->priority),
            recipientId: $row->recipient_id,
            content: $row->content,
            idempotencyKey: $row->idempotency_key,
            status: NotificationStatus::from($row->status),
            errorMessage: $row->error_message,
            gatewayId: $row->gateway_id,
            sentAt: $row->sent_at,
            deliveredAt: $row->delivered_at,
            createdAt: $row->created_at,
            updatedAt: $row->updated_at,
        );
    }
}

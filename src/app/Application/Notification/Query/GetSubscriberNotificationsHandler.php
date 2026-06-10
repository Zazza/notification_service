<?php

declare(strict_types=1);

namespace App\Application\Notification\Query;

use App\Application\Notification\DTO\NotificationDTO;
use App\Application\Notification\DTO\SubscriberNotificationsResponse;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\NotificationStatus;

readonly class GetSubscriberNotificationsHandler
{
    public function __construct(
        private NotificationRepositoryInterface $repository,
    ) {
    }

    public function handle(GetSubscriberNotificationsQuery $query): SubscriberNotificationsResponse
    {
        if ($query->status !== null) {
            $notifications = $this->repository->findByRecipientIdAndStatus(
                $query->recipientId,
                NotificationStatus::from($query->status),
            );
        } else {
            $notifications = $this->repository->findByRecipientId($query->recipientId);
        }

        return new SubscriberNotificationsResponse(
            recipientId: $query->recipientId,
            total: count($notifications),
            notifications: array_map(fn($n) => new NotificationDTO(
                id: $n->getId()->getValue(),
                channel: $n->getChannel()->value,
                priority: $n->getPriority()->value,
                status: $n->getStatus()->value,
                recipientId: $n->getRecipientId(),
                content: $n->getContent(),
                errorMessage: $n->getErrorMessage(),
                gatewayId: $n->getGatewayId(),
                retryCount: 0,
                sentAt: $n->getSentAt()?->toDateTimeString(),
                deliveredAt: $n->getDeliveredAt()?->toDateTimeString(),
                createdAt: $n->getCreatedAt()->toDateTimeString(),
                updatedAt: $n->getUpdatedAt()->toDateTimeString(),
            ), $notifications),
        );
    }
}

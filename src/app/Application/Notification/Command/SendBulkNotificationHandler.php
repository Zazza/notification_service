<?php

declare(strict_types=1);

namespace App\Application\Notification\Command;

use App\Application\Notification\DTO\BulkNotificationResponse;
use App\Application\Notification\DTO\NotificationDTO;
use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\Service\NotificationDomainService;
use App\Domain\Notification\ValueObject\Channel;
use App\Domain\Notification\ValueObject\Priority;
use App\Infrastructure\Queue\NotificationPublisherInterface;

class SendBulkNotificationHandler
{
    public function __construct(
        private readonly NotificationDomainService $domainService,
        private readonly NotificationPublisherInterface $publisher,
    ) {
    }

    public function handle(SendBulkNotificationCommand $command): BulkNotificationResponse
    {
        $channel = Channel::from($command->channel);
        $priority = Priority::from($command->priority);

        $notifications = $this->domainService->createBulkNotifications(
            $channel,
            $priority,
            $command->recipientIds,
            $command->content,
        );

        foreach ($notifications as $notification) {
            $this->publisher->publish($notification);
        }

        return new BulkNotificationResponse(
            total: count($notifications),
            notifications: array_map(
                fn(Notification $n) => $this->toDTO($n),
                $notifications,
            ),
        );
    }

    private function toDTO(Notification $n): NotificationDTO
    {
        return new NotificationDTO(
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
        );
    }
}

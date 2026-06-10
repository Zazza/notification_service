<?php

declare(strict_types=1);

namespace App\Domain\Notification\Entity;

use App\Domain\Notification\Event\NotificationCreated;
use App\Domain\Notification\Event\NotificationDelivered;
use App\Domain\Notification\Event\NotificationDiscarded;
use App\Domain\Notification\Event\NotificationSent;
use App\Domain\Notification\ValueObject\Channel;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\Notification\ValueObject\NotificationStatus;
use App\Domain\Notification\ValueObject\Priority;
use App\Infrastructure\Workflow\WorkflowSubjectTrait;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Notification
{
    use WorkflowSubjectTrait;

    private NotificationStatus $status;
    private ?string $errorMessage;
    private ?string $gatewayId;
    private CarbonInterface $createdAt;
    private ?CarbonInterface $sentAt;
    private ?CarbonInterface $deliveredAt;
    private CarbonInterface $updatedAt;

    /** @var array<object> */
    private array $events = [];

    /**
     * @param bool $raiseEvents Dispatch domain events (true on create, false on reconstitute)
     */
    private function __construct(
        private readonly NotificationId $id,
        private readonly Channel $channel,
        private readonly Priority $priority,
        private readonly string $recipientId,
        private readonly string $content,
        private readonly string $idempotencyKey,
        NotificationStatus $status,
        ?string $errorMessage,
        ?string $gatewayId,
        ?CarbonInterface $sentAt,
        ?CarbonInterface $deliveredAt,
        CarbonInterface $createdAt,
        CarbonInterface $updatedAt,
        bool $raiseEvents = true,
    ) {
        $this->status = $status;
        $this->errorMessage = $errorMessage;
        $this->gatewayId = $gatewayId;
        $this->sentAt = $sentAt;
        $this->deliveredAt = $deliveredAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->statusValue = $status->value;

        if ($raiseEvents) {
            $this->events[] = new NotificationCreated($id);
        }
    }

    public static function create(
        Channel $channel,
        Priority $priority,
        string $recipientId,
        string $content,
        string $idempotencyKey,
    ): self {
        $now = CarbonImmutable::now();

        return new self(
            id: NotificationId::generate(),
            channel: $channel,
            priority: $priority,
            recipientId: $recipientId,
            content: $content,
            idempotencyKey: $idempotencyKey,
            status: NotificationStatus::QUEUED,
            errorMessage: null,
            gatewayId: null,
            sentAt: null,
            deliveredAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        NotificationId $id,
        Channel $channel,
        Priority $priority,
        string $recipientId,
        string $content,
        string $idempotencyKey,
        NotificationStatus $status,
        ?string $errorMessage,
        ?string $gatewayId,
        ?CarbonInterface $sentAt,
        ?CarbonInterface $deliveredAt,
        CarbonInterface $createdAt,
        CarbonInterface $updatedAt,
    ): self {
        return new self(
            id: $id,
            channel: $channel,
            priority: $priority,
            recipientId: $recipientId,
            content: $content,
            idempotencyKey: $idempotencyKey,
            status: $status,
            errorMessage: $errorMessage,
            gatewayId: $gatewayId,
            sentAt: $sentAt,
            deliveredAt: $deliveredAt,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            raiseEvents: false,
        );
    }

    public function markSent(?string $gatewayId = null): void
    {
        if ($this->status !== NotificationStatus::QUEUED) {
            throw new \LogicException(sprintf(
                'Cannot transition from "%s" to "sent". Expected status: "queued".',
                $this->status->value,
            ));
        }

        $this->status = NotificationStatus::SENT;
        $this->statusValue = NotificationStatus::SENT->value;
        $this->gatewayId = $gatewayId;
        $this->sentAt = CarbonImmutable::now();
        $this->updatedAt = CarbonImmutable::now();
        $this->events[] = new NotificationSent($this->id, $gatewayId);
    }

    public function markDelivered(): void
    {
        if ($this->status !== NotificationStatus::SENT) {
            throw new \LogicException(sprintf(
                'Cannot transition from "%s" to "delivered". Expected status: "sent".',
                $this->status->value,
            ));
        }

        $this->status = NotificationStatus::DELIVERED;
        $this->statusValue = NotificationStatus::DELIVERED->value;
        $this->deliveredAt = CarbonImmutable::now();
        $this->updatedAt = CarbonImmutable::now();
        $this->events[] = new NotificationDelivered($this->id);
    }

    public function markDiscarded(string $reason): void
    {
        if ($this->status->isFinal()) {
            throw new \LogicException(sprintf(
                'Cannot transition from "%s" to "discarded". Status is already final.',
                $this->status->value,
            ));
        }

        $this->status = NotificationStatus::DISCARDED;
        $this->statusValue = NotificationStatus::DISCARDED->value;
        $this->errorMessage = $reason;
        $this->updatedAt = CarbonImmutable::now();
        $this->events[] = new NotificationDiscarded($this->id, $reason);
    }

    public function getId(): NotificationId
    {
        return $this->id;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function getRecipientId(): string
    {
        return $this->recipientId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    public function getCreatedAt(): CarbonInterface
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?CarbonInterface
    {
        return $this->sentAt;
    }

    public function getDeliveredAt(): ?CarbonInterface
    {
        return $this->deliveredAt;
    }

    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updatedAt;
    }

    /**
     * @return array<object>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}

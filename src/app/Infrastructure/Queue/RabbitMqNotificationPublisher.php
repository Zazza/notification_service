<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Notification\Entity\Notification;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMqNotificationPublisher implements NotificationPublisherInterface
{
    private ?AMQPStreamConnection $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function publish(Notification $notification): void
    {
        $channel = $this->getConnection()->channel();

        $queueName = $notification->getPriority()->getQueueName();

        $channel->queue_declare($queueName, false, true, false, false);

        $payload = json_encode([
            'notification_id' => $notification->getId()->getValue(),
            'channel' => $notification->getChannel()->value,
            'priority' => $notification->getPriority()->value,
            'recipient_id' => $notification->getRecipientId(),
            'content' => $notification->getContent(),
            'idempotency_key' => $notification->getIdempotencyKey(),
        ]);
        assert(is_string($payload));

        $msg = new AMQPMessage($payload, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'priority' => $notification->getPriority()->getWeight(),
        ]);

        $channel->basic_publish($msg, '', $queueName);
        $channel->close();
    }

    /**
     * @throws Exception
     */
    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
            );
        }

        return $this->connection;
    }
}

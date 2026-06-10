<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\Channel;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\Notification\ValueObject\Priority;
use App\Infrastructure\Deduplication\DeduplicationServiceInterface;
use App\Infrastructure\Gateway\EmailGatewayInterface;
use App\Infrastructure\Gateway\SmsGatewayInterface;
use App\Infrastructure\Gateway\GatewayResult;
use App\Infrastructure\Workflow\NotificationWorkflowManager;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class NotificationConsumer
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
        private readonly SmsGatewayInterface $smsGateway,
        private readonly EmailGatewayInterface $emailGateway,
        private readonly DeduplicationServiceInterface $deduplicationService,
        private readonly NotificationWorkflowManager $workflowManager,
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    /**
     * @throws Exception
     */
    public function consume(string $queueName = ''): void
    {
        $connection = new AMQPStreamConnection(
            $this->host,
            $this->port,
            $this->user,
            $this->password,
        );
        $channel = $connection->channel();

        $queues = $queueName !== ''
            ? [$queueName]
            : array_map(fn(Priority $p) => $p->getQueueName(), Priority::cases());

        foreach ($queues as $queue) {
            $channel->queue_declare($queue, false, true, false, false);
        }

        $callback = function (AMQPMessage $msg) {
            $this->processMessage($msg);
        };

        foreach ($queues as $queue) {
            $channel->basic_consume($queue, '', false, false, false, false, $callback);
        }

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    public function processMessage(AMQPMessage $msg): void
    {
        $payload = json_decode($msg->getBody(), true);
        $notificationId = NotificationId::fromString($payload['notification_id']);

        // Idempotency: check if already processed
        $lockKey = 'process:' . $payload['idempotency_key'];
        if (!$this->deduplicationService->acquireLock($lockKey, (int) config('notification.lock_ttl'))) {
            $msg->ack();
            return;
        }

        try {
            $notification = $this->repository->findById($notificationId);

            if ($notification === null) {
                $msg->ack();
                return;
            }

            // Validate transition: queued -> sent
            $this->workflowManager->applyTransition($notification, 'send');

            $result = $this->sendToGateway(
                Channel::from($payload['channel']),
                $payload['recipient_id'],
                $payload['content'],
            );

            if ($result->success) {
                $notification->markSent($result->gatewayId);

                // sent -> delivered
                $this->workflowManager->applyTransition($notification, 'confirm_delivery');
                $notification->markDelivered();
            } else {
                $retryCount = ($payload['retry_count'] ?? 0) + 1;

                if ($retryCount >= (int) config('notification.max_retries')) {
                    $this->workflowManager->applyDiscard($notification);
                    $notification->markDiscarded($result->errorMessage ?? 'Unknown error');
                } else {
                    // Re-queue for retry
                    $payload['retry_count'] = $retryCount;
                    $this->requeue($payload);
                    $msg->ack();
                    return;
                }
            }

            $this->repository->save($notification);
            $msg->ack();
        } catch (\Throwable $e) {
            $msg->nack(true);
        } finally {
            $this->deduplicationService->releaseLock($lockKey);
        }
    }

    private function sendToGateway(Channel $channel, string $recipientId, string $content): GatewayResult
    {
        return match ($channel) {
            Channel::SMS => $this->smsGateway->send($recipientId, $content),
            Channel::EMAIL => $this->emailGateway->send($recipientId, $content),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Exception
     */
    private function requeue(array $payload): void
    {
        $connection = new AMQPStreamConnection(
            $this->host,
            $this->port,
            $this->user,
            $this->password,
        );
        $channel = $connection->channel();

        $priority = Priority::tryFrom($payload['priority'] ?? 'normal') ?? Priority::NORMAL;
        $queueName = $priority->getQueueName();

        $body = json_encode($payload);
        assert(is_string($body));

        $msg = new AMQPMessage($body, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $channel->basic_publish($msg, '', $queueName);
        $channel->close();
        $connection->close();
    }
}

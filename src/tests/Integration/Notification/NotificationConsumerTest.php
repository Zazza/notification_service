<?php

declare(strict_types=1);

namespace Tests\Integration\Notification;

use App\Application\Notification\Command\SendBulkNotificationCommand;
use App\Application\Notification\Command\SendBulkNotificationHandler;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\NotificationId;
use App\Domain\Notification\ValueObject\NotificationStatus;
use App\Infrastructure\Deduplication\DeduplicationServiceInterface;
use App\Infrastructure\Gateway\EmailGatewayInterface;
use App\Infrastructure\Gateway\GatewayResult;
use App\Infrastructure\Gateway\SmsGatewayInterface;
use App\Infrastructure\Queue\NotificationConsumer;
use App\Infrastructure\Workflow\NotificationWorkflowManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\TestCase;

class NotificationConsumerTest extends TestCase
{
    use RefreshDatabase;

    private NotificationRepositoryInterface $repository;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(NotificationRepositoryInterface::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_consumer_processes_sms_and_marks_delivered(): void
    {
        $sendHandler = $this->app->make(SendBulkNotificationHandler::class);
        $response = $sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'high',
            recipientIds: ['consumer-sms-1'],
            content: 'Consumer SMS test ' . __METHOD__,
        ));

        $notificationId = $response->notifications[0]->id;

        $mockSms = new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::success('mock-sms-gw-' . bin2hex(random_bytes(4)));
            }
        };
        $mockEmail = new class implements EmailGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::success('mock-email-gw');
            }
        };

        $this->processMessage($notificationId, 'sms', 'high', 'consumer-sms-1', 'Consumer SMS test', 0, $mockSms, $mockEmail);

        $notification = $this->repository->findById(NotificationId::fromString($notificationId));
        $this->assertEquals(NotificationStatus::DELIVERED, $notification->getStatus());
        $this->assertNotNull($notification->getGatewayId());
        $this->assertNotNull($notification->getSentAt());
        $this->assertNotNull($notification->getDeliveredAt());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_consumer_processes_email_and_marks_delivered(): void
    {
        $sendHandler = $this->app->make(SendBulkNotificationHandler::class);
        $response = $sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'email',
            priority: 'normal',
            recipientIds: ['consumer-email-1'],
            content: 'Consumer Email test ' . __METHOD__,
        ));

        $notificationId = $response->notifications[0]->id;

        $mockSms = new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::success('mock-sms');
            }
        };
        $mockEmail = new class implements EmailGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::success('mock-email-gw-' . bin2hex(random_bytes(4)));
            }
        };

        $this->processMessage($notificationId, 'email', 'normal', 'consumer-email-1', 'Consumer Email test', 0, $mockSms, $mockEmail);

        $notification = $this->repository->findById(NotificationId::fromString($notificationId));
        $this->assertEquals(NotificationStatus::DELIVERED, $notification->getStatus());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_consumer_marks_discarded_after_max_retries(): void
    {
        $sendHandler = $this->app->make(SendBulkNotificationHandler::class);
        $response = $sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'normal',
            recipientIds: ['consumer-fail'],
            content: 'Will fail ' . __METHOD__,
        ));

        $notificationId = $response->notifications[0]->id;

        $mockSms = new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::failure('Provider unavailable');
            }
        };
        $mockEmail = new class implements EmailGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::failure('Fail');
            }
        };

        // retry_count=2 means this is the 3rd attempt, should be discarded
        $this->processMessage($notificationId, 'sms', 'normal', 'consumer-fail', 'Will fail', 2, $mockSms, $mockEmail);

        $notification = $this->repository->findById(NotificationId::fromString($notificationId));
        $this->assertEquals(NotificationStatus::DISCARDED, $notification->getStatus());
        $this->assertEquals('Provider unavailable', $notification->getErrorMessage());
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_consumer_discards_invalid_recipient_after_exhausted_retries(): void
    {
        $sendHandler = $this->app->make(SendBulkNotificationHandler::class);
        $response = $sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'high',
            recipientIds: ['INVALID-phone'],
            content: 'Bad recipient ' . __METHOD__,
        ));

        $notificationId = $response->notifications[0]->id;

        $mockSms = new class implements SmsGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                if (str_starts_with($recipientId, 'INVALID')) {
                    return GatewayResult::failure('Invalid phone number');
                }
                return GatewayResult::success('ok');
            }
        };
        $mockEmail = new class implements EmailGatewayInterface {
            public function send(string $recipientId, string $content): GatewayResult
            {
                return GatewayResult::success('ok');
            }
        };

        // retry_count=2 → 3rd attempt → discard
        $this->processMessage($notificationId, 'sms', 'high', 'INVALID-phone', 'Bad recipient', 2, $mockSms, $mockEmail);

        $notification = $this->repository->findById(NotificationId::fromString($notificationId));
        $this->assertEquals(NotificationStatus::DISCARDED, $notification->getStatus());
        $this->assertStringContainsString('Invalid', $notification->getErrorMessage());
    }

    /**
     * @throws BindingResolutionException
     */
    private function processMessage(
        string $notificationId,
        string $channel,
        string $priority,
        string $recipientId,
        string $content,
        int $retryCount,
        SmsGatewayInterface $sms,
        EmailGatewayInterface $email,
    ): void {
        $payload = json_encode([
            'notification_id' => $notificationId,
            'channel' => $channel,
            'priority' => $priority,
            'recipient_id' => $recipientId,
            'content' => $content,
            'idempotency_key' => 'key-' . $notificationId,
            'retry_count' => $retryCount,
        ]);

        $amqpMessage = $this->createMock(AMQPMessage::class);
        $amqpMessage->method('getBody')->willReturn($payload);
        $amqpMessage->method('ack')->willReturn(null);
        $amqpMessage->method('nack')->willReturn(null);

        $consumer = new NotificationConsumer(
            repository: $this->repository,
            smsGateway: $sms,
            emailGateway: $email,
            deduplicationService: $this->app->make(DeduplicationServiceInterface::class),
            workflowManager: $this->app->make(NotificationWorkflowManager::class),
            host: 'rabbitmq',
            port: 5672,
            user: 'guest',
            password: 'guest',
        );

        $consumer->processMessage($amqpMessage);
    }
}

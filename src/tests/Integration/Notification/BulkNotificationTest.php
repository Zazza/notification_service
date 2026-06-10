<?php

declare(strict_types=1);

namespace Tests\Integration\Notification;

use App\Application\Notification\Command\SendBulkNotificationCommand;
use App\Application\Notification\Command\SendBulkNotificationHandler;
use App\Domain\Notification\Exception\DuplicateNotificationException;
use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\ValueObject\NotificationStatus;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkNotificationTest extends TestCase
{
    use RefreshDatabase;

    private SendBulkNotificationHandler $handler;
    private NotificationRepositoryInterface $repository;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->app->make(SendBulkNotificationHandler::class);
        $this->repository = $this->app->make(NotificationRepositoryInterface::class);
    }

    public function test_bulk_send_creates_notifications_in_queued_status(): void
    {
        $command = new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'normal',
            recipientIds: ['user-001', 'user-002', 'user-003'],
            content: 'Test bulk message ' . __METHOD__,
        );

        $response = $this->handler->handle($command);

        $this->assertEquals(3, $response->total);
        $this->assertCount(3, $response->notifications);

        foreach ($response->notifications as $dto) {
            $this->assertEquals('queued', $dto->status);
            $this->assertEquals('sms', $dto->channel);
            $this->assertEquals('normal', $dto->priority);
            $this->assertStringContainsString('Test bulk message', $dto->content);
        }
    }

    public function test_bulk_send_persists_to_database(): void
    {
        $command = new SendBulkNotificationCommand(
            channel: 'email',
            priority: 'high',
            recipientIds: ['user-100'],
            content: 'Persist test ' . __METHOD__,
        );

        $this->handler->handle($command);

        $notifications = $this->repository->findByRecipientId('user-100');

        $this->assertCount(1, $notifications);
        $this->assertEquals(NotificationStatus::QUEUED, $notifications[0]->getStatus());
        $this->assertEquals('email', $notifications[0]->getChannel()->value);
        $this->assertEquals('high', $notifications[0]->getPriority()->value);
    }

    public function test_bulk_send_email_channel(): void
    {
        $command = new SendBulkNotificationCommand(
            channel: 'email',
            priority: 'low',
            recipientIds: ['mail-001', 'mail-002'],
            content: 'Marketing email ' . __METHOD__,
        );

        $response = $this->handler->handle($command);

        $this->assertEquals(2, $response->total);
        foreach ($response->notifications as $dto) {
            $this->assertEquals('email', $dto->channel);
            $this->assertEquals('low', $dto->priority);
        }
    }

    public function test_duplicate_notification_is_rejected(): void
    {
        $command = new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'normal',
            recipientIds: ['dup-user'],
            content: 'Duplicate test ' . __METHOD__,
        );

        $this->handler->handle($command);

        $this->expectException(DuplicateNotificationException::class);
        $this->handler->handle($command);
    }

    public function test_api_endpoint_bulk_send(): void
    {
        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'sms',
            'priority' => 'high',
            'recipient_ids' => ['api-user-1', 'api-user-2'],
            'content' => 'API test message ' . __METHOD__,
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'total',
            'data' => [
                '*' => ['id', 'channel', 'priority', 'status', 'recipientId', 'content'],
            ],
        ]);
        $response->assertJson(['total' => 2]);
    }

    public function test_api_endpoint_validates_input(): void
    {
        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'invalid',
            'priority' => 'unknown',
            'recipient_ids' => [],
            'content' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel', 'priority', 'recipient_ids', 'content']);
    }
}

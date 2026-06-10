<?php

declare(strict_types=1);

namespace Tests\Integration\Notification;

use App\Application\Notification\Command\SendBulkNotificationCommand;
use App\Application\Notification\Command\SendBulkNotificationHandler;
use App\Application\Notification\Query\GetSubscriberNotificationsQuery;
use App\Application\Notification\Query\GetSubscriberNotificationsHandler;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberQueryTest extends TestCase
{
    use RefreshDatabase;

    private SendBulkNotificationHandler $sendHandler;
    private GetSubscriberNotificationsHandler $queryHandler;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->sendHandler = $this->app->make(SendBulkNotificationHandler::class);
        $this->queryHandler = $this->app->make(GetSubscriberNotificationsHandler::class);
    }

    public function test_get_subscriber_notifications_returns_all(): void
    {
        $this->sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'normal',
            recipientIds: ['sub-001'],
            content: 'Message 1 ' . __METHOD__,
        ));

        $this->sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'email',
            priority: 'high',
            recipientIds: ['sub-001'],
            content: 'Message 2 ' . __METHOD__,
        ));

        $response = $this->queryHandler->handle(
            new GetSubscriberNotificationsQuery(recipientId: 'sub-001'),
        );

        $this->assertEquals('sub-001', $response->recipientId);
        $this->assertEquals(2, $response->total);
        $this->assertCount(2, $response->notifications);
    }

    public function test_get_subscriber_notifications_empty_for_unknown(): void
    {
        $response = $this->queryHandler->handle(
            new GetSubscriberNotificationsQuery(recipientId: 'nonexistent'),
        );

        $this->assertEquals('nonexistent', $response->recipientId);
        $this->assertEquals(0, $response->total);
        $this->assertEmpty($response->notifications);
    }

    public function test_get_subscriber_notifications_filtered_by_status(): void
    {
        $this->sendHandler->handle(new SendBulkNotificationCommand(
            channel: 'sms',
            priority: 'normal',
            recipientIds: ['sub-filter'],
            content: 'Filtered message ' . __METHOD__,
        ));

        $response = $this->queryHandler->handle(
            new GetSubscriberNotificationsQuery(recipientId: 'sub-filter', status: 'queued'),
        );

        $this->assertEquals(1, $response->total);
        $this->assertEquals('queued', $response->notifications[0]->status);
    }

    public function test_api_endpoint_subscriber_notifications(): void
    {
        $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => 'high',
            'recipient_ids' => ['api-sub-1'],
            'content' => 'API subscriber test ' . __METHOD__,
        ])->assertSuccessful();

        $response = $this->getJson('/api/notifications/subscriber/api-sub-1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'recipient_id',
            'total',
            'data' => [
                '*' => ['id', 'channel', 'priority', 'status', 'recipientId'],
            ],
        ]);
    }

    public function test_api_endpoint_subscriber_with_status_filter(): void
    {
        $this->postJson('/api/notifications/bulk', [
            'channel' => 'sms',
            'priority' => 'normal',
            'recipient_ids' => ['api-sub-2'],
            'content' => 'Filter test ' . __METHOD__,
        ])->assertSuccessful();

        $response = $this->getJson('/api/notifications/subscriber/api-sub-2?status=queued');

        $response->assertStatus(200);
        $response->assertJson(['total' => 1]);
    }
}

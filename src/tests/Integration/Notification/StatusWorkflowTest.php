<?php

declare(strict_types=1);

namespace Tests\Integration\Notification;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\ValueObject\Channel;
use App\Domain\Notification\ValueObject\NotificationStatus;
use App\Domain\Notification\ValueObject\Priority;
use App\Infrastructure\Workflow\NotificationWorkflowManager;
use Illuminate\Contracts\Container\BindingResolutionException;
use Tests\TestCase;

class StatusWorkflowTest extends TestCase
{
    private NotificationWorkflowManager $workflow;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = $this->app->make(NotificationWorkflowManager::class);
    }

    private function createNotification(): Notification
    {
        return Notification::create(
            Channel::SMS,
            Priority::NORMAL,
            'test-recipient',
            'Test message',
            'test-idempotency-' . uniqid(),
        );
    }

    public function test_initial_status_is_queued(): void
    {
        $notification = $this->createNotification();
        $this->assertEquals(NotificationStatus::QUEUED, $notification->getStatus());
    }

    public function test_transition_queued_to_sent(): void
    {
        $notification = $this->createNotification();
        $this->assertTrue($this->workflow->canTransition($notification, 'send'));
        $this->workflow->applyTransition($notification, 'send');
        $notification->markSent('gateway-123');

        $this->assertEquals(NotificationStatus::SENT, $notification->getStatus());
        $this->assertEquals('gateway-123', $notification->getGatewayId());
    }

    public function test_transition_sent_to_delivered(): void
    {
        $notification = $this->createNotification();
        $this->workflow->applyTransition($notification, 'send');
        $notification->markSent('gateway-456');

        $this->workflow->applyTransition($notification, 'confirm_delivery');
        $notification->markDelivered();

        $this->assertEquals(NotificationStatus::DELIVERED, $notification->getStatus());
    }

    public function test_discard_from_queued(): void
    {
        $notification = $this->createNotification();
        $this->assertTrue($this->workflow->canDiscard($notification));

        $this->workflow->applyDiscard($notification);
        $notification->markDiscarded('Invalid number');

        $this->assertEquals(NotificationStatus::DISCARDED, $notification->getStatus());
    }

    public function test_discard_from_sent(): void
    {
        $notification = $this->createNotification();
        $this->workflow->applyTransition($notification, 'send');
        $notification->markSent('gateway-789');

        $this->assertTrue($this->workflow->canDiscard($notification));

        $this->workflow->applyDiscard($notification);
        $notification->markDiscarded('Delivery failed');

        $this->assertEquals(NotificationStatus::DISCARDED, $notification->getStatus());
    }

    public function test_cannot_send_from_delivered(): void
    {
        $notification = $this->createNotification();
        $this->workflow->applyTransition($notification, 'send');
        $notification->markSent('gw-1');
        $this->workflow->applyTransition($notification, 'confirm_delivery');
        $notification->markDelivered();

        $this->assertFalse($this->workflow->canTransition($notification, 'send'));
    }

    public function test_cannot_send_from_discarded(): void
    {
        $notification = $this->createNotification();
        $this->workflow->applyDiscard($notification);
        $notification->markDiscarded('reason');

        $this->assertFalse($this->workflow->canTransition($notification, 'send'));
    }

    public function test_invalid_transition_throws_exception(): void
    {
        $notification = $this->createNotification();
        $this->expectException(\LogicException::class);
        $this->workflow->applyTransition($notification, 'confirm_delivery');
    }

    public function test_full_happy_path(): void
    {
        $notification = $this->createNotification();

        $this->workflow->applyTransition($notification, 'send');
        $notification->markSent('gw-happy');
        $this->assertEquals(NotificationStatus::SENT, $notification->getStatus());

        $this->workflow->applyTransition($notification, 'confirm_delivery');
        $notification->markDelivered();
        $this->assertEquals(NotificationStatus::DELIVERED, $notification->getStatus());
        $this->assertTrue($notification->getStatus()->isFinal());
    }
}

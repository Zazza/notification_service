<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Domain\Notification\Service\NotificationDomainService;
use App\Infrastructure\Deduplication\DeduplicationServiceInterface;
use App\Infrastructure\Deduplication\InMemoryDeduplicationService;
use App\Infrastructure\Deduplication\RedisDeduplicationService;
use App\Infrastructure\Gateway\EmailGatewayInterface;
use App\Infrastructure\Gateway\MockEmailGateway;
use App\Infrastructure\Gateway\MockSmsGateway;
use App\Infrastructure\Gateway\SmsGatewayInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentNotificationRepository;
use App\Infrastructure\Queue\NotificationPublisherInterface;
use App\Infrastructure\Queue\RabbitMqNotificationPublisher;
use App\Infrastructure\Queue\SyncNotificationPublisher;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository
        $this->app->bind(
            NotificationRepositoryInterface::class,
            EloquentNotificationRepository::class,
        );

        // Gateways (mock implementations)
        $this->app->bind(SmsGatewayInterface::class, MockSmsGateway::class);
        $this->app->bind(EmailGatewayInterface::class, MockEmailGateway::class);

        // Queue publisher
        $this->app->bind(NotificationPublisherInterface::class, function ($app) {
            return match (config('notification.publisher')) {
                'sync' => new SyncNotificationPublisher(),
                default => new RabbitMqNotificationPublisher(
                    host: config('services.rabbitmq.host'),
                    port: (int) config('services.rabbitmq.port'),
                    user: config('services.rabbitmq.user'),
                    password: config('services.rabbitmq.password'),
                ),
            };
        });

        // Deduplication
        $this->app->bind(DeduplicationServiceInterface::class, function ($app) {
            return match (config('notification.deduplication_driver')) {
                'memory' => new InMemoryDeduplicationService(),
                default => new RedisDeduplicationService(
                    (static function (): \Redis {
                        $redis = new \Redis();
                        $redis->connect(
                            config('database.redis.default.host'),
                            (int) config('database.redis.default.port'),
                        );
                        return $redis;
                    })(),
                ),
            };
        });

        // Domain service
        $this->app->bind(NotificationDomainService::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Notification\Command\SendBulkNotificationCommand;
use App\Application\Notification\Command\SendBulkNotificationHandler;
use App\Application\Notification\Query\GetSubscriberNotificationsQuery;
use App\Application\Notification\Query\GetSubscriberNotificationsHandler;
use App\Domain\Notification\Exception\DuplicateNotificationException;
use App\Presentation\Http\Request\SendBulkNotificationRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'API для массовой отправки уведомлений (SMS/Email) с поддержкой очередей RabbitMQ, дедупликации через Redis и CQRS',
    title: 'Broadcast Notification Service API',
    contact: new OA\Contact(email: 'dev@broadcast-service.local'),
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'API Server',
)]
#[OA\Tag(
    name: 'Notifications',
    description: 'Управление уведомлениями',
)]
readonly class NotificationController
{
    public function __construct(
        private SendBulkNotificationHandler $sendHandler,
        private GetSubscriberNotificationsHandler $queryHandler,
    ) {
    }

    #[OA\Post(
        path: '/api/notifications/bulk',
        description: 'Создаёт уведомления для списка получателей и ставит их в очередь на отправку',
        summary: 'Массовая отправка уведомлений',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'priority', 'recipient_ids', 'content'],
                properties: [
                    new OA\Property(
                        property: 'channel',
                        description: 'Канал отправки',
                        type: 'string',
                        example: 'email',
                        enum: ['sms', 'email'],
                    ),
                    new OA\Property(
                        property: 'priority',
                        description: 'Приоритет уведомления',
                        type: 'string',
                        example: 'normal',
                        enum: ['high', 'normal', 'low'],
                    ),
                    new OA\Property(
                        property: 'recipient_ids',
                        description: 'Список ID получателей',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['user-001', 'user-002', 'user-003'],
                    ),
                    new OA\Property(
                        property: 'content',
                        description: 'Текст уведомления',
                        type: 'string',
                        example: 'Ваш заказ успешно оформлен!',
                        maxLength: 5000,
                    ),
                ],
            ),
        ),
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Уведомления успешно поставлены в очередь',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Notifications queued successfully'),
                        new OA\Property(property: 'total', type: 'integer', example: 3),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'channel', type: 'string', example: 'email'),
                                    new OA\Property(property: 'priority', type: 'string', example: 'normal'),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                    new OA\Property(property: 'recipient_id', type: 'string', example: 'user-001'),
                                    new OA\Property(property: 'content', type: 'string', example: 'Ваш заказ успешно оформлен!'),
                                    new OA\Property(property: 'error_message', type: 'string', example: null, nullable: true),
                                    new OA\Property(property: 'gateway_id', type: 'string', example: null, nullable: true),
                                    new OA\Property(property: 'retry_count', type: 'integer', example: 0),
                                    new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', example: null, nullable: true),
                                    new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', example: null, nullable: true),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-09T12:00:00Z'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-09T12:00:00Z'),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 409,
                description: 'Дубликат уведомления',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Duplicate notification'),
                        new OA\Property(property: 'message', type: 'string', example: 'Notification already exists for this recipient'),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The channel field is required.'),
                        new OA\Property(
                            property: 'errors',
                            properties: [
                                new OA\Property(
                                    property: 'channel',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'Канал обязателен'),
                                ),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function sendBulk(SendBulkNotificationRequest $request): JsonResponse
    {
        $command = new SendBulkNotificationCommand(
            channel: $request->input('channel'),
            priority: $request->input('priority'),
            recipientIds: $request->input('recipient_ids'),
            content: $request->input('content'),
        );

        try {
            $response = $this->sendHandler->handle($command);
        } catch (DuplicateNotificationException $e) {
            return new JsonResponse([
                'error' => 'Duplicate notification',
                'message' => $e->getMessage(),
            ], 409);
        }

        return new JsonResponse([
            'message' => 'Notifications queued successfully',
            'total' => $response->total,
            'data' => $response->notifications,
        ], 202);
    }

    #[OA\Get(
        path: '/api/notifications/subscriber/{recipientId}',
        description: 'Возвращает список всех уведомлений для указанного получателя с опциональной фильтрацией по статусу',
        summary: 'Получить уведомления подписчика',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'recipientId',
                description: 'ID получателя',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Фильтр по статусу уведомления',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'sent', 'delivered', 'failed']),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список уведомлений',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'recipient_id', type: 'string', example: 'user-001'),
                        new OA\Property(property: 'total', type: 'integer', example: 2),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'channel', type: 'string', example: 'email'),
                                    new OA\Property(property: 'priority', type: 'string', example: 'normal'),
                                    new OA\Property(property: 'status', type: 'string', example: 'sent'),
                                    new OA\Property(property: 'recipient_id', type: 'string', example: 'user-001'),
                                    new OA\Property(property: 'content', type: 'string', example: 'Ваш заказ успешно оформлен!'),
                                    new OA\Property(property: 'error_message', type: 'string', example: null, nullable: true),
                                    new OA\Property(property: 'gateway_id', type: 'string', example: 'msg-abc123', nullable: true),
                                    new OA\Property(property: 'retry_count', type: 'integer', example: 0),
                                    new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', example: '2026-06-09T12:01:00Z'),
                                    new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', example: null, nullable: true),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-09T12:00:00Z'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-09T12:01:00Z'),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Получатель не найден',
            ),
        ],
    )]
    public function getSubscriberNotifications(string $recipientId): JsonResponse
    {
        $status = request()->query('status');
        $query = new GetSubscriberNotificationsQuery(
            recipientId: $recipientId,
            status: is_string($status) ? $status : null,
        );

        $response = $this->queryHandler->handle($query);

        return new JsonResponse([
            'recipient_id' => $response->recipientId,
            'total' => $response->total,
            'data' => $response->notifications,
        ]);
    }
}

# Broadcast Service

Сервис уведомлений с поддержкой каналов **SMS** и **Email**, очередью **RabbitMQ**, statemachine и идемпотентностью.
Построен на **Laravel 11** с архитектурой **DDD + CQRS**.

---

## Порядок реализации
1) Структура проекта (докеры, Makefile, composer.json).
   Взял за основу прошлые проекты, из-за старых версий часть не работала.
   Обновил - что-то конфликтовало, что-=то недоступно из-за блокировок, а в других проектах изменился принцип установки (git, pecl, repo).
   Чтобы уныло не гуглить - использовал ИИ для решения этих вопросов
2) Создал структуру в Laravel (DDD за основу).
3) Многие части уже реализовал прежде (на Phalcon и Symfony). Использовал наработки. От себя добавил StateMachine для статусов 
4) С помощью ИИ создал интеграционные тесты, они используются мной, в первую очередь, для проверки работоспособности.
5) Поправил что нужно. Затем попросил ИИ проверить корректность кода.
6) Swagger не писал руками (это долго) - опять ИИ. Проверил корректность глазами и добавил в Makefile.
7) Для надежности добавил phpstan и cs в проект. Увидел замечания и поправил с помощью ИИ.
8) Сделал итоговое ревью кода и догенерировал README.md.


## Замечания
Реализовать всё в рамках тестового задания не возможно. Чтобы я добавил:
- DLQ (dead-letter queue)
- RabbitMQ в продакшне не самое лучшее решение, но в проекте соблюдается D(из SOLID) поэтому подменить реализацию не сложно
- Юнит тесты
- idempotency тут чисто для "галочки", проверять надо не только дедупы
- Rate Limit
- ну и дял прода: авторизация, метрики, логирование и тп


## Архитектура

Проект следует принципам **Domain-Driven Design** с разделением слоёв:

```
src/app/
├── Domain/                  # Доменный слой — бизнес-логика и правила
│   └── Notification/
│       ├── Entity/          # Сущности: Notification
│       ├── ValueObject/     # VO: Channel, NotificationId, NotificationStatus, Priority
│       ├── Event/           # Доменные события: Created, Sent, Delivered, Discarded
│       ├── Exception/       # Доменные исключения
│       ├── Repository/      # Интерфейсы репозиториев (контракты)
│       └── Service/         # Доменные сервисы: NotificationDomainService
│
├── Application/             # Слой приложения — оркестрация use cases (CQRS)
│   └── Notification/
│       ├── Command/         # Команды (запись): SendBulkNotification*
│       ├── Query/           # Запросы (чтение): GetSubscriberNotifications*
│       └── DTO/             # Объекты передачи данных: запросы и ответы API
│
├── Infrastructure/          # Инфраструктурный слой — техническая реализация
│   ├── Persistence/         # Eloquent-репозитории и модели БД
│   ├── Queue/               # RabbitMQ publisher/consumer, sync-заглушка
│   ├── Gateway/             # Шлюзы SMS/Email (интерфейсы + mock-реализации)
│   ├── Deduplication/       # Идемпотентность через Redis / in-memory
│   └── Workflow/            # Symfony Workflow — машина состояний уведомления
│
└── Presentation/            # Слой представления — точка входа HTTP
    └── Http/
        ├── Controller/      # REST-контроллеры: NotificationController
        └── Request/         # Form Request — валидация входящих данных
```

### Поток данных

```
HTTP Request
  → Presentation\Controller
    → Application\Command\Handler (или Query\Handler)
      → Domain\Service → Domain\Entity (бизнес-логика, события)
        → Infrastructure\Persistence (сохранение)
        → Infrastructure\Queue (отправка в RabbitMQ)
          → Infrastructure\Gateway (доставка SMS/Email)
```

### Машина состояний уведомления

```
queued → sent → delivered
  └─────→ discarded
```

Переходы управляются через **Symfony Workflow**. Некорректные переходы выбрасывают `InvalidStatusTransitionException`.

---

## Стек

| Компонент       | Технология          |
|-----------------|---------------------|
| Framework       | Laravel 11 (PHP 8.3)|
| База данных     | PostgreSQL 15       |
| Очередь         | RabbitMQ 3          |
| Кэш / Дедуп     | Redis 7             |
| State Machine   | Symfony Workflow    |
| Frontend        | Vite + TailwindCSS  |
| Контейнеры      | Docker Compose      |

---

## Быстрый старт

### Требования

- Docker + Docker Compose
- Make (опционально)

### Команды

```bash
# Сборка и запуск всех сервисов
make build          # docker compose build
make up             # docker compose up -d

# Полная инициализация (build → up → composer → migrate)
make init

# Остановить все сервисы
make down
```

### После первого запуска

```bash
# Установить зависимости
make composer install

# Запустить миграции
make migrate

# Перезапустить воркер очереди
make queue-restart
```

### Запуск без Make

```bash
docker compose build
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan migrate
```

---

## API

### Отправка уведомлений

```
POST /api/notifications/bulk
```

### Получение уведомлений подписчика

```
GET /api/notifications/subscriber/{recipientId}
```

---

## Тесты

Тесты запускаются внутри контейнера `app` с изолированным окружением (SQLite, sync-queue, array-cache).

```bash
# Все тесты
docker compose exec app php artisan test

# В рамках тестового задания реализованы только интеграционные тесты
docker compose exec app php artisan test --testsuite=Integration

# Конкретный файл
docker compose exec app php artisan test tests/Integration/Notification/BulkNotificationTest.php
```

### Структура тестов

```
src/tests/
└── Integration/         # Интеграционные тесты — полный поток (CQRS + очередь + БД)
    └── Notification/
        ├── BulkNotificationTest.php      # Массовая отправка
        ├── NotificationConsumerTest.php  # Обработка из очереди
        ├── StatusWorkflowTest.php        # Машина состояний
        └── SubscriberQueryTest.php       # Запросы по подписчику
```

### Swagger

```bash

make swagger

```
URL: http://localhost/api/documentation
---

## Ключевые возможности

- **Идемпотентность** — повторная отправка с тем же `idempotency_key` не создаёт дубликат (Redis)
- **Приоритеты** — `high`, `default`, `low` влияют на порядок обработки
- **Ретраи** — автоматический повтор с экспоненциальной задержкой при ошибках шлюза
- **Дедупликация** — предотвращает повторную обработку одного и того же уведомления
- **Множественные каналы** — SMS и Email через шлюзы с возможностью подмены реализации
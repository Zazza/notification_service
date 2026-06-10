.PHONY: build up down restart stop start init composer artisan migrate queue logs test cache-clear fix-perms swagger stan cs cs-fix lint

COMPOSE = docker compose

# Service names from docker-compose.yml
SVC_APP    := app
SVC_CLI    := cli
SVC_NGINX  := nginx
SVC_WORKER := worker

build:
	$(COMPOSE) build

up: .env
	$(COMPOSE) up -d

.env:
	@if [ ! -f .env ]; then cp .env.example .env && echo "Created .env from .env.example"; fi
	@if [ ! -f src/.env ]; then cp src/.env.example src/.env && echo "Created src/.env from src/.env.example"; fi

down:
	$(COMPOSE) down --remove-orphans

restart:
	$(COMPOSE) restart

stop:
	$(COMPOSE) stop

start:
	$(COMPOSE) start

init: build up
	@echo "=== Installing Laravel into ./src ==="
	$(COMPOSE) exec $(SVC_APP) sh -c "\
		if [ ! -f artisan ]; then \
			composer create-project laravel/laravel /tmp/laravel && \
			cp -a /tmp/laravel/. /var/www/html/ && \
			rm -rf /tmp/laravel; \
		fi"
	@echo "=== Installing additional packages ==="
	$(COMPOSE) exec $(SVC_APP) sh -c "\
		composer require php-amqplib/php-amqplib predis/predis"
	@echo "=== Generating key ==="
	$(COMPOSE) exec $(SVC_APP) php artisan key:generate
	@echo "=== Running migrations ==="
	$(COMPOSE) exec $(SVC_APP) php artisan migrate
	@echo "=== DONE ==="

composer:
	$(COMPOSE) exec $(SVC_APP) composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	$(COMPOSE) exec $(SVC_APP) php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	$(COMPOSE) exec $(SVC_APP) php artisan migrate

queue:
	$(COMPOSE) restart $(SVC_WORKER)

logs:
	$(COMPOSE) logs -f --tail=100

test:
	$(COMPOSE) run --rm $(SVC_CLI) php artisan test $(filter-out $@,$(MAKECMDGOALS))

cache-clear:
	$(COMPOSE) exec $(SVC_APP) php artisan optimize:clear

fix-perms:
	$(COMPOSE) exec $(SVC_APP) chmod -R 777 storage bootstrap/cache

swagger:
	$(COMPOSE) exec $(SVC_APP) php artisan l5-swagger:generate

# Static analysis
stan:
	$(COMPOSE) run --rm $(SVC_CLI) vendor/bin/phpstan analyse

# Code style check
cs:
	$(COMPOSE) run --rm $(SVC_CLI) vendor/bin/phpcs

# Code style fix
cs-fix:
	$(COMPOSE) run --rm $(SVC_CLI) vendor/bin/phpcbf

# Run all linters
lint: stan cs

%:
	@:

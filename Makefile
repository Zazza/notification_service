.PHONY: build up down restart stop start init composer artisan migrate queue

COMPOSE = docker compose
APP = notification_service_app
CLI = notification_service_cli
NGINX = notification_service_nginx

build:
	$(COMPOSE) build

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down --remove-orphans

restart:
	$(COMPOSE) restart

stop:
	$(COMPOSE) stop

start:
	$(COMPOSE) start

init: build up
	@echo "=== Installing Laravel into ./app ==="
	$(COMPOSE) exec $(CLI) sh -c "\
		if [ ! -f artisan ]; then \
			composer create-project laravel/laravel /tmp/laravel && \
			cp -a /tmp/laravel/. /var/www/html/ && \
			rm -rf /tmp/laravel; \
		fi"
	@echo "=== Installing additional packages ==="
	$(COMPOSE) exec $(CLI) sh -c "\
		composer require php-amqplib/php-amqplib predis/predis"
	@echo "=== Generating key ==="
	$(COMPOSE) exec $(CLI) php artisan key:generate
	@echo "=== Running migrations ==="
	$(COMPOSE) exec $(CLI) php artisan migrate
	@echo "=== DONE ==="

composer:
	$(COMPOSE) exec $(CLI) composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	$(COMPOSE) exec $(CLI) php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	$(COMPOSE) exec $(CLI) php artisan migrate

queue:
	$(COMPOSE) restart worker

logs:
	$(COMPOSE) logs -f --tail=100

%:
	@:

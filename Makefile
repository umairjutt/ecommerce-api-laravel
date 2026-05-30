.PHONY: up down install migrate fresh test horizon

up: ; docker compose up -d
down: ; docker compose down
install: ; docker compose exec app composer install && docker compose exec app php artisan key:generate
migrate: ; docker compose exec app php artisan migrate
fresh: ; docker compose exec app php artisan migrate:fresh --seed
test: ; docker compose exec app ./vendor/bin/pest
horizon: ; docker compose exec horizon php artisan horizon:status

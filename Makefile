.PHONY: setup dev frontend build types lint test e2e docker-up docker-down migrate deploy rollback

setup:
	composer install
	@test -f .env || cp .env.example .env
	php artisan key:generate
	pnpm install --frozen-lockfile

dev:
	php artisan dev

frontend:
	pnpm dev

build:
	pnpm build

types:
	pnpm types:check

lint:
	pnpm lint

test:
	composer test

e2e:
	pnpm test:e2e

docker-up:
	docker compose up --build -d

deploy:
	./scripts/deploy.sh

rollback:
	./scripts/rollback.sh

docker-down:
	docker compose down

migrate:
	docker compose exec app php artisan migrate --force

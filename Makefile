.PHONY: up down logs ps fixtures test lint e2e check

## Build and start the whole stack on http://localhost:8080
up:
	[ -f .env ] || cp .env.example .env
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

ps:
	docker compose ps

## Seed products, attributes and images with test data (replaces existing data)
fixtures:
	docker compose exec app php bin/console app:fixtures:load

## Backend unit + integration tests (separate products_test database)
test:
	docker compose run --rm --build tests

## PHPStan + PHP CS Fixer
lint:
	docker compose run --rm --build tests sh -c 'vendor/bin/phpstan analyse --memory-limit=512M --no-progress && vendor/bin/php-cs-fixer fix --dry-run --diff'

## Browser e2e tests against the running stack (needs `make up` and Node 22)
e2e:
	cd frontend && npm ci && npx playwright install chromium && npx playwright test

check: lint test e2e

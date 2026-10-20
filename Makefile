.PHONY: up down logs ps

up:
	[ -f .env ] || cp .env.example .env
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

ps:
	docker compose ps

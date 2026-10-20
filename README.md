# Импорт товаров из .xlsx

Тестовое задание: загрузка `.xlsx` → асинхронный импорт через очередь → список и карточка товара.

**Стек:** Slim 4 + Doctrine ORM 3 · Symfony Messenger + RabbitMQ · PostgreSQL 16 · Angular 21 + NgRx + Angular Material · nginx · Docker Compose.

## Быстрый старт

Нужен Docker с Compose v2.

```bash
make up          # cp .env.example .env + docker compose up -d --build
```

| Что | Где |
|---|---|
| Приложение | http://localhost:8080 — вход `admin@example.com` / `admin123` |
| Swagger UI | http://localhost:8080/api/docs/ |
| RabbitMQ UI | http://localhost:15672 — `app` / `app` |

Дальше: «Импорт» → выбрать `import example (2) Фул.xlsx` из корня репозитория → «Импортировать». Прогресс и отчёт об ошибках видны на той же странице, товары — в разделе «Товары».

Демо-данные без импорта: `make fixtures` (заменяет текущие товары).

## Команды

| Команда | Что делает |
|---|---|
| `make up` / `make down` | Поднять / остановить стек |
| `make logs`, `make ps` | Логи, состояние контейнеров |
| `make fixtures` | Сидеры: 30 товаров с атрибутами и изображениями |
| `make test` | PHPUnit: unit + интеграционные + функциональные тесты API (БД `products_test`) |
| `make lint` | PHPStan (level 6) + PHP CS Fixer |
| `make e2e` | Playwright e2e в браузере против запущенного стека (нужен Node 22) |

## Как устроено

```
browser ──► nginx ──┬─ /            Angular SPA (собрана в образе nginx)
                    ├─ /api/docs/   Swagger UI + docs/openapi.yaml
                    ├─ /storage/    скачанные изображения (общий volume)
                    └─ /api/*  ───► app (php-fpm, Slim) ──► PostgreSQL
                                        │ POST /api/imports
                                        ▼
                                    RabbitMQ ──► worker (messenger:consume) ──► PostgreSQL, storage
```

**Импорт.** `POST /api/imports` проверяет файл (расширение `.xlsx`, размер ≤ 10 МБ, MIME по содержимому, читаемость PhpSpreadsheet), сохраняет его, создаёт запись в `import_jobs` и отправляет сообщение в RabbitMQ; ответ `202` приходит сразу. Воркер читает строки, валидирует каждую, скачивает изображения и делает upsert по `external_code` — товар, атрибуты и изображения пишутся в одной транзакции. Невалидная строка или недоступная картинка не прерывают импорт, а попадают в отчёт с номером строки. Клиент опрашивает `GET /api/imports/{id}` раз в секунду.

**Маппинг колонок:** `Внешний код` → `external_code`, `Наименование` → `name`, `Описание` → `description`, `Цена: Цена продажи` → `price`; `discount = (цена − «Закупочная цена») / цена × 100`; все колонки `Доп. поле: …` → `product_attributes`; ссылки из `Доп. поле: Ссылка на упаковку` и `Доп. поле: Ссылки на фото` (через запятую) → `product_images` (`url` — исходная ссылка, `path` — локальная копия или `null`, если скачать не удалось).

### Backend (`backend/`)

| Слой | Где |
|---|---|
| Контроллеры (принять запрос → делегировать → ответ) | `src/Controller/` |
| Сервисы импорта: загрузка, чтение xlsx, валидация строки, скачивание изображений, upsert | `src/Import/` |
| Сообщение и обработчик очереди | `src/Message/` |
| Репозитории (все запросы к БД) | `src/Repository/` |
| Сущности и миграции | `src/Entity/`, `migrations/` |
| JWT-авторизация | `src/Auth/` |
| DI-контейнер, маршруты | `config/container.php`, `config/app.php` |
| Сидеры | `src/Fixtures/`, команда `app:fixtures:load` |

### Frontend (`frontend/src/app/`)

| Что | Где |
|---|---|
| Интерфейсы `Product`, `ProductAttribute`, `ProductImage`, `ImportJob` | `models/` |
| `ProductService`, `ImportService`, `AuthService` (HttpClient только здесь) | `services/` |
| NgRx: `loadProducts*`, effect через `ProductService`, `selectAllProducts` / `selectProductsLoading` / `selectTotalPages` | `store/products/` |
| HTTP-interceptor (JWT, 401 → логин, сеть/5xx/429 → уведомление), `CanActivate` guard, глобальный `ErrorHandler` | `core/` |
| Общее состояние импорта: прогресс в шапке, уведомление о завершении | `services/import-tracker.service.ts` |
| Маршруты с lazy loading standalone-компонентов | `app.routes.ts` |
| Страницы: вход, список, карточка, импорт | `pages/` |

**Оформление.** UI-кит — Angular Material, перекрашенный под собственную дизайн-систему (`frontend/src/styles.scss`): тёплые «бумажные» поверхности, тёмно-зелёный акцент, Geist для интерфейса и Georgia для заголовков, иконки Material Symbols. Токены Material (`--mat-sys-*` и overrides компонентов) заданы из той же палитры, поэтому кнопки, поля, пагинатор, таблицы и уведомления выглядят единообразно. Вёрстка адаптивная (проверено на 390 px без горизонтальной прокрутки).

Список товаров берёт параметры (`page`, `limit`, `name`, `price_min`, `price_max`) из URL — ссылку на отфильтрованную страницу можно отправить, «назад» работает. Компонент получает данные только через `store.select()` + `async`.

## API

Полное описание — `docs/openapi.yaml` (Swagger UI: `/api/docs/`). Все `/api/*`, кроме `health` и `auth/login`, требуют `Authorization: Bearer <jwt>`.

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/health` | Проверка БД и RabbitMQ (`200` / `503`) |
| `POST` | `/api/auth/login` | `{email, password}` → `{token, expiresAt}` |
| `POST` | `/api/imports` | multipart `file` → `202` + задача импорта |
| `GET` | `/api/imports/{id}` | Статус, прогресс, отчёт об ошибках |
| `GET` | `/api/products?page=1&limit=20&name=&price_min=&price_max=` | Серверная пагинация и фильтры |
| `GET` | `/api/products/{id}` | Карточка: поля, атрибуты, изображения |

Rate limiting (nginx `limit_req`): `POST /api/imports` и `POST /api/auth/login` — 10 запросов в минуту с IP (всплеск до 5), сверх — `429`.

## Тесты и качество

- **Unit:** парсинг и валидация строки xlsx (`tests/Unit`).
- **Интеграционные (PostgreSQL):** CRUD товаров с атрибутами и изображениями, каскадное удаление, уникальность `external_code`, поиск/пагинация, сидеры; конвейер импорта целиком — RabbitMQ заменён in-memory транспортом, HTTP — mock-клиентом (`tests/Integration`).
- **Функциональные API:** реальное Slim-приложение без веб-сервера — авторизация, пагинация, фильтры, валидация параметров и файла, постановка импорта в очередь (`tests/Functional`).
- **e2e (Playwright):** вход, импорт файла-примера с прогрессом и отчётом, прогресс в шапке на других страницах и уведомление о завершении, пагинация и фильтры, карточка, уведомление при обрыве сети (`frontend/e2e`).
- **PHPStan** level 6 с `phpstan-doctrine` (`backend/phpstan.neon`), **PHP CS Fixer** PER-CS 2.0 (`backend/.php-cs-fixer.php`).
- **GitHub Actions** (`.github/workflows/ci.yml`): PHPStan, CS Fixer, миграции на пустой БД, PHPUnit; сборка фронтенда; сборка Docker-образов, запуск стека и e2e.

Интеграционные тесты работают только с базой, имя которой оканчивается на `_test`, и создают её сами.

## Переменные окружения

Все переменные с описанием — в `.env.example`.

| Переменная | Назначение |
|---|---|
| `APP_DEBUG` | `1` — детали ошибок в ответах API |
| `HTTP_PORT` | Порт nginx на хосте (по умолчанию 8080) |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | PostgreSQL |
| `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_UI_PORT` | RabbitMQ; DSN для Messenger собирается в `docker-compose.yml` |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Учётная запись для входа |
| `JWT_SECRET` | Ключ подписи JWT, ≥ 32 символов (`openssl rand -hex 32`) |

## Решения и упрощения

- **Безопасность скачивания изображений.** Ссылки берутся из загруженного пользователем файла, поэтому запросы к внутренним адресам (`db`, `rabbitmq`, `169.254.169.254`, приватные сети) блокируются (`NoPrivateNetworkHttpClient`), разрешены только `http(s)`, тип файла определяется по содержимому, размер ≤ 10 МБ. Одинаковые ссылки скачиваются один раз.
- **Один администратор из `.env`** вместо таблицы пользователей — для тестового задания достаточно; токен хранится в `localStorage`.
- **Rate limiting на уровне nginx** — единственная точка входа в стек; при запуске PHP без nginx ограничение не действует.
- **Индикатор прогресса и отчёт об ошибках** — подробно на странице импорта; пока импорт идёт, полоса прогресса видна в шапке на любой странице (в том числе в карточке товара), а по завершении всплывает уведомление с кнопкой «Отчёт». Состояние импорта общее (`ImportTrackerService`) и переживает перезагрузку страницы.
- **Обработка ошибок на фронтенде:** нет связи с сервером, ошибки 5xx и 429 — уведомление (HTTP-interceptor); потеря и восстановление сети — уведомление; непредвиденные ошибки — глобальный `ErrorHandler`; ошибки валидации и «не найдено» показываются на самой странице. Единичный сбой опроса статуса импорта не прерывает отслеживание.
- **Angular 21**, а не 22: Angular CLI 22 требует Node ≥ 22.22.3.
- Если запись товара упадёт на непредвиденной ошибке БД, импорт завершается со статусом `failed`, а воркер перезапускается с новым соединением. Невалидные данные отсекаются до записи и так не срабатывают.

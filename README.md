# dzencode.loc

## Stack

- **Backend** — Laravel 12 (PHP 8.3-FPM), MySQL 8.0, Redis
- **Real-time** — Laravel Reverb (Pusher-protocol WebSocket server) + Laravel Echo on the client
- **Queue** — RabbitMQ (raw `php-amqplib`, not a Laravel queue driver), with a Redis-backed Laravel queue as the fallback/retry path when RabbitMQ can't be used directly — see `backend/CLAUDE.md` § Queue
- **Frontend** — Vue 3 + TypeScript + Vite, Pinia, Vue Router, `vue-toastification` (global error toasts)
- **Rate limiting** — Laravel's built-in `throttle` middleware on `POST`/`GET /api/comments`, keyed by IP
- **Web server** — Nginx (templates in `_docker/nginx/conf.d/templates`)
- **Prod proxy** — Caddy (auto-HTTPS, `docker-compose.prod.yml`)
- **Infra** — Docker Compose

## Running (dev)

```
cp .env.example .env
cp backend/.env.example backend/.env
make up
```

- Site: http://127.0.0.1:8880
- API: http://127.0.0.1:8880/api/
- Websocket (Reverb): ws://127.0.0.1:6001

## Makefile commands

| Command                                                                                                     | What it does                                                                                                                                    |
|-------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|
| `make up`                                                                                                   | start the main stack (nginx, app, scheduler, reverb, rabbitmq, queue, outbox, redis, db)                                                        |
| `make site`                                                                                                 | Vue dev server with HMR on :5173 (`--profile site`)                                                                                             |
| `make pma`                                                                                                  | phpMyAdmin on :8080 (`--profile phpmyadmin`)                                                                                                    |
| `make rabbitmq-ui`                                                                                          | RabbitMQ management UI on :15672 (`--profile rabbitmq-ui`)                                                                                      |
| `make prod`                                                                                                 | production stack with Caddy                                                                                                                     |
| `make down`                                                                                                 | stop everything, including profile-gated services                                                                                               |
| `make bash`                                                                                                 | shell into the `app` container                                                                                                                  |
| `make logs`                                                                                                 | all services, tailed together                                                                                                                   |
| `make logs-nginx` / `logs-app` / `logs-db` / `logs-reverb` / `logs-rabbitmq` / `logs-queue` / `logs-outbox` | single-service logs                                                                                                                             |
| `make logs-laravel`                                                                                         | tail today's `backend/storage/logs/<Y-m>/laravel-<Y-m-d>.log` (computed inside the container, not the host — see `backend/CLAUDE.md` § Logging) |
| `make scheduler-restart` / `queue-restart` / `outbox-restart`                                               | restart one worker container (needed after code changes — see note below)                                                                       |
| `make failed-jobs`                                                                                          | list fallback/retry jobs that exhausted their retries (`php artisan queue:failed`)                                                              |
| `make reverb-keys`                                                                                          | rotate Reverb app id/key/secret in `backend/.env` and `frontend/.env`                                                                           |
| `make fresh`                                                                                                | `migrate:fresh --seed`                                                                                                                          |
| `make test`                                                                                                 | `php artisan test`                                                                                                                              |
| `make format`                                                                                               | Laravel Pint                                                                                                                                    |

## Services

| Service             | Container              | Port                  | Notes                                                                                                        |
|---------------------|------------------------|-----------------------|--------------------------------------------------------------------------------------------------------------|
| nginx               | dzencode_nginx         | `${SITE_PORT}` (8880) |                                                                                                              |
| app (PHP-FPM)       | dzencode_app           | internal, 9000        |                                                                                                              |
| scheduler           | dzencode_scheduler     | —                     | `schedule:work`                                                                                              |
| reverb              | dzencode_reverb        | 6001                  | WebSocket broadcasting                                                                                       |
| rabbitmq            | dzencode_rabbitmq      | 5672                  | broker only — management UI not published by default                                                         |
| queue               | dzencode_queue         | —                     | `comments:consume`, the RabbitMQ consumer that actually creates comments                                     |
| outbox              | dzencode_outbox        | —                     | `queue:work redis`, fallback/retry worker for comments RabbitMQ couldn't be used for directly                |
| redis               | dzencode_redis         | 6379                  | cache + outbox queue, AOF persistence enabled                                                                |
| db (MySQL 8)        | dzencode_db            | 8101 → 3306           |                                                                                                              |
| frontend-site (dev) | dzencode_frontend_site | 5173                  | `make site` only                                                                                             |
| phpmyadmin          | dzencode_phpmyadmin    | 8080                  | `make pma` only                                                                                              |
| rabbitmq-ui         | dzencode_rabbitmq_ui   | 15672                 | `make rabbitmq-ui` only — thin proxy to the broker's own management plugin, kept out of `make prod` entirely |

## Structure

| Path                          | What's there                                                                                                     |
|-------------------------------|------------------------------------------------------------------------------------------------------------------|
| `_docker/`                    | Docker build context — Dockerfile, entrypoint scripts, nginx/Caddy config                                        |
| ....`app/`                    | shared Dockerfile + entrypoint scripts for every backend-based container (app, scheduler, reverb, queue, outbox) |
| ....`caddy/`                  | production reverse-proxy config (Caddyfile)                                                                      |
| ....`nginx/conf.d/templates/` | nginx vhost templates                                                                                            |
|                               |                                                                                                                  |
| `backend/`                    | Laravel application                                                                                              |
| ....`app/`                    |                                                                                                                  |
| ........`Console/Commands/`   | long-running or one-off Artisan commands                                                                         |
| ........`Events/`             | things broadcast to the frontend in real time                                                                    |
| ........`Http/`               |                                                                                                                  |
| ............`Controllers/`    | HTTP request handlers                                                                                            |
| ............`Requests/`       | validation rules and query filters                                                                               |
| ............`Resources/`      | model → API response shape                                                                                       |
| ........`Jobs/`               | units of work dispatched onto a queue                                                                            |
| ........`Logging/`            | custom log handlers                                                                                              |
| ........`Models/`             | Eloquent models                                                                                                  |
| ........`Services/`           | business logic                                                                                                   |
| ........`Traits/`             | reusable behavior shared across services/controllers                                                             |
| ....`config/`                 | app and package configuration                                                                                    |
| ....`routes/`                 | route definitions (API, broadcast channels, console)                                                             |
| ....`tests/Feature/`          | feature/integration tests                                                                                        |
|                               |                                                                                                                  |
| `frontend/`                   | Vue 3 SPA                                                                                                        |
| ....`src/`                    |                                                                                                                  |
| ........`assets/`             | global CSS/SCSS entry point, static assets bundled by Vite                                                       |
| ........`components/ui/`      |                                                                                                                  |
| ............`base/`           | generic UI pieces not tied to any one feature                                                                    |
| ............`<domain>/`       | components specific to one feature (e.g. comments)                                                               |
| ........`composables/`        | reusable reactive logic shared across components                                                                 |
| ........`plugins/`            | app-wide setup (HTTP client, websocket connection, ...)                                                          |
| ........`router/`             | route definitions                                                                                                |
| ........`stores/`             | Pinia stores                                                                                                     |
| ........`types/`              | shared TypeScript interfaces for fetched data shapes                                                             |
| ........`utils/`              | standalone helper functions, no component/Vue state involved                                                     |
| ........`views/`              | route-level pages                                                                                                |
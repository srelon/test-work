# Test work

A comments platform: a public, anonymous (no accounts/auth) threaded comment feed with a Laravel API backend and a Vue 3 SPA frontend. One level of nesting — top-level comments and replies to them, with an optional "replying to a specific reply" reference within a thread. New comments and replies appear on every open browser tab in real time over a WebSocket, without a page refresh.

## Features

- **Threaded comments** — post a top-level comment or reply to one (one level deep); optionally address a specific earlier reply within the thread (shown as "↳ Replying to ...")
- **Sorting & pagination** — top-level comments sorted by newest/oldest or by user name/email, paginated server-side
- **Real-time updates** — a newly posted comment or reply shows up live in every other open tab (Laravel Reverb + Echo), with a "New comments" section and a scroll-to/flash highlight for the just-arrived item
- **Rich text** — a small whitelist of inline formatting (bold, italic, inline code, links) in the comment editor, sanitized identically on both the client and the server before it's ever rendered
- **Image attachment** — attach one image per comment, client-side cropped/resized before upload; the server independently re-validates and re-encodes it via GD (rejects anything that isn't a real, decodable raster image — no SVG/vector formats, no disguised payloads)
- **Spam protection** — Google reCAPTCHA v2 Checkbox on the comment form, verified server-side; rate limiting (`throttle` middleware) on all endpoints, tighter on submission than on reading
- **Async, resilient submission** — a new comment is queued (RabbitMQ) rather than written synchronously in the request; if RabbitMQ and/or Redis are unavailable, a circuit breaker with escalating backoff routes around them (a Laravel-queue fallback job, then direct synchronous persistence) instead of the request failing outright
- **HTML sanitization / XSS safety** — user-submitted rich text is restricted to a strict tag whitelist, enforced independently on both sides

## Stack

- **Backend** — Laravel 12 (PHP 8.3-FPM), MySQL 8.0, Redis
- **Real-time** — Laravel Reverb (Pusher-protocol WebSocket server) + Laravel Echo on the client
- **Queue** — RabbitMQ (raw `php-amqplib`, not a Laravel queue driver), with a Redis-backed Laravel queue as the fallback/retry path when RabbitMQ can't be used directly — see `backend/CLAUDE.md` § Queue
- **Frontend** — Vue 3 + TypeScript + Vite, Pinia, Vue Router, `vue-toastification` (global error toasts)
- **Rate limiting** — Laravel's built-in `throttle` middleware on `POST`/`GET /api/comments`, keyed by IP
- **Spam protection** — Google reCAPTCHA v2 Checkbox on the comment form, verified server-side — see `backend/CLAUDE.md` § reCAPTCHA
- **Web server** — Nginx (templates in `_docker/nginx/conf.d/templates`)
- **Prod proxy** — Caddy (auto-HTTPS, `docker-compose.prod.yml`)
- **Infra** — Docker Compose

## Libraries

Framework/tooling defaults (Laravel, Vue, Vite, TypeScript, Tailwind, Pinia, Vue Router, Axios) are covered by **Stack** above — this is only what was pulled in for a specific feature.

**Backend:**

- `laravel/reverb` — self-hosted WebSocket broadcasting server behind the real-time updates
- `php-amqplib/php-amqplib` — raw AMQP client for publishing to and consuming from RabbitMQ
- `predis/predis` — Redis client, backing the cache/queue fallback path when RabbitMQ can't be used directly

**Frontend:**

- `laravel-echo` + `pusher-js` — subscribes to the Reverb WebSocket channel for live comment updates
- `vee-validate` + `yup` — comment form validation
- `@tiptap/*` — the rich-text editor behind the comment composer (bold/italic/inline code/links)
- `vue-advanced-cropper` — client-side image cropping before upload
- `vue-toastification` — global error toasts

## Database schema

The app's own data model is a self-referencing `comments` table plus a `contacts` table it points at — `parent_id` (FK to `comments.id`, cascade-deletes replies with their parent) links a reply to its top-level comment, `replied_to_comment_id` (plain column, no FK — matches the reference design this project follows) optionally points at the specific reply being addressed within that same thread, and `contact_id` (FK to `contacts.id`) points at the commenter's name/email, deduplicated across comments from the same person instead of repeating them on every row.

## Running (dev)

```
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d --build
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d
```
`make fresh` if you need to recreate seed

- Site: http://127.0.0.1:8880
- API: http://127.0.0.1:8880/api/
- Websocket (Reverb): ws://127.0.0.1:6001

## Deployment (production)

`docker-compose.prod.yml` is applied on top of the base `docker-compose.yml`, not a replacement for it — the base file stays plain-HTTP local dev. It adds one `caddy` container that reverse-proxies `:80`/`:443` to the existing `nginx` service and obtains its own Let's Encrypt certificate automatically (HTTP-01 challenge, so ports 80/443 need to be reachable from the internet).

```
# set SSL_DOMAIN in the root .env first
make prod
```

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

# dzencode.loc

## Stack

- **Backend** — Laravel (PHP 8.3-FPM), MySQL 8.0, Redis
- **Frontend** — Vue 3 + TypeScript + Vite, Pinia, Vue Router
- **Websocket** — Node.js 20 (`ws`, `ioredis`, `pino`)
- **Web server** — Nginx (templates in `_docker/nginx/conf.d/templates`)
- **Prod proxy** — Caddy (auto-HTTPS, `docker-compose.prod.yml`)
- **Infra** — Docker Compose

## Structure

```
backend/     Laravel application
frontend/    Vue 3 SPA
websocket/   Node.js websocket server
_docker/     Dockerfile, nginx templates, entrypoint scripts, Caddyfile
```

## Running (dev)

```
cp .env.example .env
make up
```

- Site: http://127.0.0.1:8880
- API: http://127.0.0.1:8880/api/
- Websocket: ws://127.0.0.1:6001

## Makefile commands

| Command                                                                | What it does                                                         |
|-------------------------------------------------------------------------|-----------------------------------------------------------------------|
| `make up`                                                              | start the main stack (nginx, app, scheduler, websocket, redis, db)  |
| `make site`                                                            | Vue dev server with HMR on :5173 (`--profile site`)                 |
| `make pma`                                                             | phpMyAdmin on :8080 (`--profile phpmyadmin`)                        |
| `make prod`                                                            | production stack with Caddy                                         |
| `make down`                                                            | stop everything, including profile-gated services                   |
| `make bash`                                                            | shell into the `app` container                                      |
| `make logs` / `logs-nginx` / `logs-app` / `logs-db` / `logs-websocket` | per-service logs                                                    |
| `make fresh`                                                           | `migrate:fresh --seed`                                              |
| `make test`                                                            | `php artisan test`                                                  |
| `make format`                                                          | Laravel Pint                                                        |

## Services

| Service               | Container                | Port                    |
|-----------------------|---------------------------|--------------------------|
| nginx                 | dzencode_nginx           | `${SITE_PORT}` (8880)  |
| app (PHP-FPM)         | dzencode_app             | internal, 9000          |
| scheduler             | dzencode_scheduler       | —                       |
| websocket             | dzencode_websocket       | 6001                    |
| redis                 | dzencode_redis           | 6379                    |
| db (MySQL 8)          | dzencode_db              | 8101 → 3306             |
| frontend-site (dev)   | dzencode_frontend_site   | 5173                    |
| phpmyadmin            | dzencode_phpmyadmin      | 8080                    |

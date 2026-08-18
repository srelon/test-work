# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**This file covers only monorepo-wide conventions (Docker, environment, code style).** Service-specific conventions live in nested files, loaded automatically when working in that directory:
- `backend/CLAUDE.md` — Laravel API conventions
- `frontend/CLAUDE.md` — Vue 3 site conventions

## Architecture

Monorepo with three independent services:

- `backend/` — Laravel 12 API only, no admin panel, no Livewire
- `frontend/` — Vue 3 + TypeScript + Vite (public site)
- `websocket/` — Node.js WebSocket server (ws + ioredis + pino)

All services run in Docker. The entire stack is mounted as volumes — no image rebuilds needed for code changes, only for dependency changes.

## Docker

```bash
# First run / after Dockerfile changes
docker compose up -d --build

# Normal start
make up

# Stop everything
make down

# Start Vue dev server (port 5173)
make site

# Drop and recreate the database
make fresh

# Production: HTTPS via Caddy in front of nginx (needs SSL_DOMAIN set in .env)
make prod
```

**Container names:** `dzencode_app`, `dzencode_nginx`, `dzencode_db`, `dzencode_redis`, `dzencode_scheduler`, `dzencode_websocket`; `dzencode_caddy` in production only.

**Ports:** site + API on `SITE_PORT` (`.env`, default `8880`) — API at `/api/`; Vue dev server (`make site`) — `5173`; phpMyAdmin — `8080`; WebSocket — `6001`; MySQL — `8101`.

**Production HTTPS** is `docker-compose.prod.yml`, applied on top of the base file (`make prod`), not a change to `docker-compose.yml` itself — that file stays the plain-HTTP local-dev setup. It adds one `caddy` container (`_docker/caddy/Caddyfile`) that reverse-proxies `443`/`80` to the existing `nginx` service, obtaining its own Let's Encrypt certificate automatically. Requires `SSL_DOMAIN` set in the root `.env` and ports 80/443 reachable from the internet (Let's Encrypt's HTTP-01 challenge).

## Shell scripts and permissions

`_docker/app/entrypoint.sh` and `scheduler-entrypoint.sh` are called via `sh script.sh` in `docker-compose.yml` — **do not** change this to a direct path call, as files created on Windows lose the `+x` bit. The `sh` wrapper bypasses this.

**Bind-mounted directories shared by two containers running as different users can end up with mismatched ownership** — e.g. `frontend/` is written by both `app` (uid 1000, builds `frontend/dist` in its entrypoint) and `frontend-site` (root by default, `make site`'s dev server). If one writes there first, the other can hit `EACCES` on its own `npm install`. Fix is a one-off `chown`, not a permanent `user:` pin baked into compose — check who actually needs to write there before reaching for that.

## Environment

Root `.env` controls Docker (ports, MySQL credentials). Backend has its own `backend/.env` for Laravel (`DB_HOST=db`, `REDIS_HOST=redis`).

## Real-time

Redis pub/sub is meant to connect backend to the websocket server: PHP publishes to a Redis channel → `websocket/server.js` subscribes and broadcasts to connected clients over `ws`.

**Not wired up on the backend side yet.** `websocket/channels/index.js` already handles subscribe/unsubscribe/relay against Redis — a backend feature that needs live updates just needs to `Redis::publish(channel, payload)` (or use Laravel's broadcasting layer) to the same channel name a client has subscribed to. No private-channel auth exists yet (no ticket/ownership check) — every channel is effectively public right now. Design that before exposing anything user-specific over a channel.

## Code Style Rules — ALWAYS follow these

Applies across PHP/TS/Vue — the whole codebase, not just one service.

**English only** — all code comments, docblocks, and inline notes must be in English. This also applies to every documentation file in the repo (README, CLAUDE.md itself) — nothing checked into the repo should be in a non-English language, regardless of what language is used to address Claude in conversation.

**NO alignment spaces.** Single space before `=`, `=>`, `:` — never pad to align columns. This applies to code only (PHP/TS/Vue) — in Markdown docs, alignment/padding for readability (e.g. lining up `|` columns in a table) is fine, since it isn't code.

```php
// WRONG
$output   = trim(...);
'title'   => $this->title,

// RIGHT
$output = trim(...);
'title' => $this->title,
```

**NO objects/arrays on one line.** Every property on its own line, always — no exceptions, even for short objects.

```ts
// WRONG
{ key: 'id', text: 'ID' },

// RIGHT
{
    key: 'id',
    text: 'ID',
},
```

**Same rule for multi-argument function/method calls, not just object/array literals** — a call with 2+ arguments where any argument is non-trivial (an object/array literal, or a callback with a block body `{ ... }` rather than a single-expression arrow) goes one argument per line, closing paren on its own line. A short single-expression callback with no trailing options object can stay inline.

```ts
// WRONG
watch(() => route.query, fetch_products, { immediate: true, deep: true })

// RIGHT
watch(
    () => route.query,
    fetch_products,
    {
        immediate: true,
        deep: true,
    },
)
```

**snake_case** for all variables, object keys, props, interface fields. camelCase/PascalCase only for functions, components, file names.

**No explanatory/rationale comments in code, even for non-obvious decisions.** Context about *why* something was built a certain way belongs in a service's `CLAUDE.md`, not in a `//` line next to the code. If a decision is worth flagging, say it in the chat reply so it can be routed to docs deliberately, not left as a stray comment.

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**This file covers only monorepo-wide conventions (Docker, environment, code style).** Service-specific conventions live in nested files, loaded automatically when working in that directory:
- `backend/CLAUDE.md` — Laravel API conventions
- `frontend/CLAUDE.md` — Vue 3 site conventions

## Architecture

Monorepo with two services plus Laravel's own broadcasting server:

- `backend/` — Laravel 12 API only, no admin panel, no Livewire; also runs Laravel Reverb (`reverb` container) for real-time broadcasting
- `frontend/` — Vue 3 + TypeScript + Vite (public site)

There is no separate custom websocket service — real-time went through a hand-rolled Node/`ws`/Redis-pub-sub relay early on, but was replaced with Laravel Reverb + Laravel Echo per spec. See § Real-time.

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

**Container names:** `dzencode_app`, `dzencode_nginx`, `dzencode_db`, `dzencode_redis`, `dzencode_scheduler`, `dzencode_reverb`; `dzencode_caddy` in production only.

**Ports:** site + API on `SITE_PORT` (`.env`, default `8880`) — API at `/api/`; Vue dev server (`make site`) — `5173`; phpMyAdmin — `8080`; Reverb (WebSocket) — `6001`; MySQL — `8101`.

**Production HTTPS** is `docker-compose.prod.yml`, applied on top of the base file (`make prod`), not a change to `docker-compose.yml` itself — that file stays the plain-HTTP local-dev setup. It adds one `caddy` container (`_docker/caddy/Caddyfile`) that reverse-proxies `443`/`80` to the existing `nginx` service, obtaining its own Let's Encrypt certificate automatically. Requires `SSL_DOMAIN` set in the root `.env` and ports 80/443 reachable from the internet (Let's Encrypt's HTTP-01 challenge).

## Shell scripts and permissions

`_docker/app/entrypoint.sh` and `scheduler-entrypoint.sh` are called via `sh script.sh` in `docker-compose.yml` — **do not** change this to a direct path call, as files created on Windows lose the `+x` bit. The `sh` wrapper bypasses this.

**Bind-mounted directories shared by two containers running as different users can end up with mismatched ownership** — e.g. `frontend/` is written by both `app` (uid 1000, builds `frontend/dist` in its entrypoint) and `frontend-site` (root by default, `make site`'s dev server). If one writes there first, the other can hit `EACCES` on its own `npm install`. Fix is a one-off `chown`, not a permanent `user:` pin baked into compose — check who actually needs to write there before reaching for that.

**`app`/`scheduler`'s `$HOME` is `/var/www`** (the `www-data` user's home dir, set in the Dockerfile) — which is the bind-mounted project root. Any tool that defaults to writing into `$HOME` (bash's own `.bash_history` on an interactive `make bash` exit, most obviously) leaks a stray file straight into the repo. `docker-compose.yml` sets `HISTFILE=/tmp/.bash_history` on both services specifically to keep bash's history inside the container instead. If a *new* `$HOME`-based leak turns up (a different tool's cache/history file), redirect that tool's own env var the same way rather than gitignoring the leaked file — the goal is that the file never gets created here at all, not that git ignores it.

## Environment

Root `.env` controls Docker (ports, MySQL credentials). Backend has its own `backend/.env` for Laravel (`DB_HOST=db`, `REDIS_HOST=redis`).

## Real-time

Real-time is Laravel Reverb (self-hosted, Pusher-protocol-compatible broadcasting server, part of `backend/`) on the server side and Laravel Echo (`laravel-echo` + `pusher-js`) on the client — this is a spec requirement, not a from-scratch choice. There is no custom Node websocket relay and no manual `Redis::publish()` — Reverb owns the whole connection lifecycle itself; Redis is only involved if `REVERB_SCALING_ENABLED` is turned on to sync multiple Reverb instances, which isn't the case here (single instance).

**Wired up for comments, the first real-time feature — the pattern to repeat for the next one:** an `App\Events\*` event implements `ShouldBroadcastNow` (not `ShouldBroadcast` — this project has no dedicated queue worker running broadcast jobs, so a queued event would silently never send; `Now` runs it synchronously in the request) and defines `broadcastOn()` (a plain `new Channel(...)` — private/presence channels need auth wiring that doesn't exist yet, see below), `broadcastAs()` (the wire event name, e.g. `'comment.created'`), and `broadcastWith()` (the payload array). `CommentService::create()` just calls `CommentCreated::dispatch($comment)` — no separate Listener needed, Laravel's broadcasting integration picks up `ShouldBroadcast*` automatically. On the frontend, `plugins/echo.ts` sets up one shared `Echo` instance (`broadcaster: 'reverb'`); a page-scoped component subscribes with `echo.channel(name).listen('.event.name', handler)` (the **leading dot is required** — it tells Echo not to prefix the event name with the `App.Events` namespace) and cleans up with `echo.leaveChannel(name)` on unmount — see `views/Home.vue`.

No private-channel auth exists yet (`routes/channels.php` is empty, no `App\Models\User` in this project at all) — every channel is effectively public right now, fine for comments (already public/anonymous). A future feature needing a private/presence channel needs real auth wired through `routes/channels.php`'s `Broadcast::channel()` before it's safe to use.

**Docker/env specifics:** the `reverb` container shares `backend/`'s Dockerfile/image and runs `php artisan reverb:start` (via `_docker/app/reverb-entrypoint.sh`, same wait-for-vendor pattern as `scheduler-entrypoint.sh`) — needs the `pcntl` and `sockets` PHP extensions (already added to `_docker/app/Dockerfile`; Reverb's signal handling throws `Undefined constant SIGINT` without `pcntl`). `REVERB_HOST`/`REVERB_PORT` in `backend/.env` are the address the **backend PHP process** uses to reach Reverb over the Docker network (`reverb:6001`, the Docker service name) — this is a *different* value from `VITE_REVERB_HOST`/`VITE_REVERB_PORT` in `frontend/.env` (`127.0.0.1:6001`, the host-mapped port a browser on the Windows host actually connects to), the same "container-name-for-server-to-server vs. `127.0.0.1`-for-browser" split already used for `VITE_API_URL` vs. `APP_URL`. `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` (separate from `REVERB_HOST`/`REVERB_PORT`) control what the server actually binds to (`0.0.0.0:6001`) — don't conflate the two pairs.

## Dependencies

When adding several new npm packages in one sitting, install them in **one** `npm install pkgA pkgB pkgC` call, not one `npm install` per package. Installing one at a time leaves the lockfile poorly deduped (nested duplicate entries instead of hoisted ones) — confirmed by comparison: 155→184 packages after a batch of additions should move a lockfile by roughly a hundred lines, not double it. If a lockfile ever looks abnormally large, `rm -rf node_modules package-lock.json && npm install` (as the same uid that normally owns the directory) resolves it.

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

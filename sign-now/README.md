# Sign-Now

A Dockerized document e-signing app (Laravel 12 / PHP 8.3) — upload a PDF, place signature and field stamps, and sign, with live updates over a Node.js WebSocket server.

## Prerequisites

- **Docker** (Engine with BuildKit)
- **Docker Compose v2** (`docker compose`)
- Host ports free: **8027, 3027, 8127, 8227, 3327, 6427, 1027**

## Run

```bash
docker compose up -d --build
```

The first build is slower — the PHP image compiles extensions and installs LibreOffice. After the containers are up, apply migrations (no entrypoint does this automatically):

```bash
docker compose exec app php artisan migrate --force
```

Fix storage permissions and seed the admin account:

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app php artisan db:seed --class=AdminSeeder
```

The seeder reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` from the environment (defaults: `admin@signnow.local` / `password`) - change these for anything non-local.

Then open **http://localhost:8027** (admin login: **http://localhost:8027/admin/login**).

## Services

| Service | Image / Build | Purpose | Port (host:container) | URL |
|---------|---------------|---------|-----------------------|-----|
| `app` (`signnow-app`) | build `./laravel-app` (`php:8.3-fpm` + LibreOffice, gd, intl, pdo_mysql, redis) | PHP-FPM app server | internal (9000) | — |
| `queue` (`signnow-queue`) | build `./laravel-app` (same image) | Runs `php artisan queue:work redis --tries=3 --timeout=120` | internal | — |
| `nginx` (`signnow-nginx`) | `nginx:alpine` | Web server / entrypoint to the app | 8027:80 | http://localhost:8027 |
| `node` (`signnow-node`) | build `./socket-server` (`node:20-alpine`) | WebSocket server for live updates | 3027:3027 | ws://localhost:3027 |
| `mysql` (`signnow-mysql`) | `mysql:8.0` | Database | 3327:3306 | `127.0.0.1:3327` |
| `redis` (`signnow-redis`) | `redis:7-alpine` | Queue / cache / session store | 6427:6379 | `127.0.0.1:6427` |
| `phpmyadmin` (`signnow-phpmyadmin`) | `phpmyadmin` | DB admin UI | 8127:80 | http://localhost:8127 |
| `mailhog` (`signnow-mailhog`) | `mailhog/mailhog` | Captures outgoing dev mail | 8227:8025, 1027:1025 | http://localhost:8227 |

All services share the `signnow` bridge network.

## Environment

Copy `.env.example` to `.env` and `laravel-app/.env.example` to `laravel-app/.env` (Docker service hostnames: `DB_HOST=mysql`, `REDIS_HOST=redis`, `MAIL_HOST=mailhog`), then run `php artisan key:generate` inside the `app` container.

Compose reads the following from a root `.env` (defaults applied if unset), used by the `mysql` and `phpmyadmin` services:

| Key | Default | Purpose |
|-----|---------|---------|
| `DB_DATABASE` | `signnow` | MySQL database name |
| `DB_USERNAME` | `signnow` | MySQL app user |
| `DB_PASSWORD` | `secret` | MySQL app password |
| `DB_ROOT_PASSWORD` | `rootsecret` | MySQL root password |

The `node` service reads fixed values from compose: `REDIS_HOST=redis`, `REDIS_PORT=6379`, `WS_PORT=3027`.

Optional Laravel keys (set in `laravel-app/.env`): `SENDGRID_API_KEY` (for production mail via `MAIL_MAILER=sendgrid`), `WEBHOOK_DEFAULT_URL` (webhook fired when a document completes), and the `AWS_*` keys for S3 storage.

## Volumes & persistence

Named volumes (survive `docker compose down`, removed by `down -v`):

- `mysql_data` → `/var/lib/mysql` (database)
- `redis_data` → `/data` (Redis persistence)
- `docsign_files` → `/var/www/html/storage/app/private/docsign` (uploaded/signed documents; shared by `app` and `queue`)

Bind mounts:

- `./laravel-app` → `/var/www/html` (app source, shared by `app`, `queue`, `nginx`)
- `./docker/php/local.ini` → PHP config
- `./docker/nginx/default.conf` → Nginx site config

## Common commands

```bash
docker compose logs -f                 # tail all logs
docker compose logs -f app             # tail one service
docker compose ps                      # status of all services

docker compose up -d                   # start (no rebuild)
docker compose up -d --build           # rebuild images and start
docker compose restart app             # restart a service

docker compose down                    # stop + remove containers (keeps volumes)
docker compose down -v                 # also wipe DB, Redis, and uploaded documents

docker compose exec app php artisan migrate --force   # run migrations
docker compose exec app php artisan optimize:clear     # clear caches (do this on HTTP 500)
docker compose exec app bash                           # shell into the app container
```

## Verify

```bash
curl -I http://localhost:8027            # app        -> 302 (redirect to login)
curl -I http://localhost:8027/register   # register   -> 200
curl -I http://localhost:8127            # phpMyAdmin -> 200
curl -I http://localhost:8227            # MailHog    -> 200
docker compose ps                        # all 8 containers Up; mysql + redis healthy
```

Outgoing email is captured by MailHog at **http://localhost:8227**.

Seed optional demo data (dev only): `docker compose exec app php artisan db:seed --class=DemoDocumentSeeder`

## Troubleshooting

- **Port already in use:** change the left-hand port in `docker-compose.yml` (e.g. `"9027:80"`).
- **HTTP 500:** `docker compose exec app php artisan optimize:clear` then `php artisan key:generate`; check `storage/logs/laravel.log`.
- **Containers exit on start / storage not writable:** `docker compose exec app chmod -R 775 storage bootstrap/cache`.
- **MySQL connection errors on first boot:** MySQL needs 10-20 seconds to initialise; wait and retry.
- **WebSocket not connecting on dashboard:** check `docker compose ps`, `docker compose logs -f node`, and `docker compose exec redis redis-cli ping` (expect `PONG`).


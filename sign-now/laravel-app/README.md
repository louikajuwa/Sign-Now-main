# Sign Now

Document signing application built with Laravel, MySQL, Redis, and Docker.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows, Mac, or Linux)
- No other local dependencies needed - everything runs inside containers.

## Quick Start (First Time)

### 1. Clone and configure environment

```bash
cd sign-now
cp laravel-app/.env.example laravel-app/.env
```

Open `laravel-app/.env` and set these values:

```dotenv
APP_NAME=SignNow
APP_URL=http://localhost:8027

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=signnow
DB_USERNAME=signnow
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Local development: emails go to MailHog (http://localhost:8227)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_FROM_ADDRESS=noreply@signnow.test
MAIL_FROM_NAME=SignNow

WEBSOCKET_URL=ws://localhost:3027
```

### 2. Start Docker containers

```bash
cd sign-now
docker compose up -d --build
```

This starts 7 containers:

| Container | Purpose | Local Port |
|---|---|---|
| `signnow-app` | PHP-FPM (Laravel) | - |
| `signnow-queue` | Queue worker (emails, webhooks) | - |
| `signnow-nginx` | Web server | **8027** |
| `signnow-node` | WebSocket server (real-time dashboard) | **3027** |
| `signnow-mysql` | MySQL 8.0 database | 3327 |
| `signnow-redis` | Redis 7 (cache, queue, sessions) | 6427 |
| `signnow-mailhog` | Email catcher (dev only) | **8227** |
| `signnow-phpmyadmin` | Database admin UI | **8127** |

### 3. Install dependencies and set up database

```bash
docker exec signnow-app composer install
docker exec signnow-app php artisan key:generate
docker exec signnow-app php artisan migrate
```

### 4. Set file permissions

```bash
docker exec signnow-app chmod -R 775 storage bootstrap/cache
docker exec signnow-app chown -R www-data:www-data storage bootstrap/cache
```

### 5. Open the app

- **App:** http://localhost:8027
- **MailHog (emails):** http://localhost:8227
- **phpMyAdmin:** http://localhost:8127

Register a new account at http://localhost:8027/register to get started.

---

## Database Management (phpMyAdmin)

phpMyAdmin is included in the Docker stack for browsing and managing the MySQL database through a web UI.

| | |
|---|---|
| **URL** | http://localhost:8127 |
| **Server** | `mysql` (pre-filled) |
| **Username** | `signnow` |
| **Password** | `secret` |

These match the `DB_USERNAME` and `DB_PASSWORD` in your `.env` file. phpMyAdmin auto-logs in using these credentials — just open the URL and you're in.

**What you can do:**
- Browse all tables (documents, fields, recipients, customers, users, audit_logs, etc.)
- Run SQL queries directly
- Export/import data (CSV, SQL)
- View and edit rows

If you changed `DB_USERNAME` or `DB_PASSWORD` in your `.env`, update the matching `PMA_USER` and `PMA_PASSWORD` values in `docker-compose.yml` under the `phpmyadmin` service.

---

## Running Again (After First Setup)

Just start the containers - no rebuild needed:

```bash
cd sign-now
docker compose up -d
```

Stop everything:

```bash
docker compose down
```

Stop and remove all data (database, uploads, cache):

```bash
docker compose down -v
```

---

## Going Live (Production)

When you're ready to deploy to a server, you only need to change the `.env` file. No code changes required.

### 1. Update `.env` for production

```dotenv
# ── App ──────────────────────────────────────────────
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sign.yourdomain.com

# ── Database ─────────────────────────────────────────
DB_DATABASE=signnow
DB_USERNAME=your_db_user
DB_PASSWORD=your_strong_password

# ── Email (SendGrid) ────────────────────────────────
MAIL_MAILER=sendgrid
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=SignNow
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxx

# ── WebSocket ────────────────────────────────────────
WEBSOCKET_URL=wss://sign.yourdomain.com/ws

# ── Webhook (optional) ──────────────────────────────
WEBHOOK_DEFAULT_URL=https://your-api.com/webhook/signing-complete
```

### 2. Key changes explained

| Setting | Local | Production |
|---|---|---|
| `APP_URL` | `http://localhost:8027` | `https://sign.yourdomain.com` |
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `MAIL_MAILER` | `smtp` (MailHog) | `sendgrid` |
| `SENDGRID_API_KEY` | (empty) | Your SendGrid API key |
| `MAIL_FROM_ADDRESS` | `noreply@signnow.test` | A SendGrid-verified sender address |
| `WEBSOCKET_URL` | `ws://localhost:3027` | `wss://sign.yourdomain.com/ws` |

### 3. Cache config for production

After updating `.env`, rebuild the config cache:

```bash
docker exec signnow-app php artisan config:cache
docker exec signnow-app php artisan route:cache
docker exec signnow-app php artisan view:cache
```

### 4. HTTPS / Reverse proxy

In production, place a reverse proxy (Nginx, Caddy, or a cloud load balancer) in front of Docker to handle SSL. The container still listens on port 80 internally. Example Nginx site config:

```nginx
server {
    listen 443 ssl http2;
    server_name sign.yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/sign.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sign.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8027;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }

    location /ws {
        proxy_pass http://127.0.0.1:3027;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

---

## SendGrid Setup

1. Create a free SendGrid account at https://sendgrid.com
2. Go to **Settings > API Keys** and create a key with "Mail Send" permission
3. Go to **Settings > Sender Authentication** and verify your sending domain or single sender address
4. Set `SENDGRID_API_KEY` and `MAIL_FROM_ADDRESS` in `.env`
5. Set `MAIL_MAILER=sendgrid`

---

## Environment Variable Reference

| Variable | Required | Description |
|---|---|---|
| `APP_URL` | Yes | Full URL where the app is accessible. Used in email links. |
| `DB_PASSWORD` | Yes | MySQL password |
| `MAIL_MAILER` | Yes | `smtp` for local (MailHog), `sendgrid` for production |
| `MAIL_FROM_ADDRESS` | Yes | Sender address for all emails |
| `SENDGRID_API_KEY` | Production | SendGrid API key (only needed when `MAIL_MAILER=sendgrid`) |
| `WEBHOOK_DEFAULT_URL` | No | URL notified when any document completes signing. Per-template URLs override this. |
| `WEBSOCKET_URL` | No | WebSocket server URL for real-time dashboard updates |

---

## Useful Commands

```bash
# View logs
docker logs signnow-app -f
docker logs signnow-queue -f

# Run migrations
docker exec signnow-app php artisan migrate

# Restart queue worker (after code changes)
docker restart signnow-queue

# Open a shell inside the app container
docker exec -it signnow-app bash

# Clear all caches
docker exec signnow-app php artisan optimize:clear
```

---

## Architecture

```
sign-now/
├── laravel-app/          # Laravel application
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Mail/             # SigningInvite, DocumentCompleted, SendGridTransport
│   │   ├── Models/           # Document, Field, Recipient, Customer, User
│   │   ├── Jobs/             # DocumentCompletedWebhook
│   │   └── Services/        # CompletionEmailService, SigningTokenService
│   ├── public/
│   │   ├── js/               # builder.js, fill.js, dashboard.js
│   │   └── css/              # docsign.css
│   ├── resources/views/
│   └── .env
├── socket-server/        # Node.js WebSocket server
├── docker/
│   ├── nginx/default.conf
│   └── php/local.ini
└── docker-compose.yml
```

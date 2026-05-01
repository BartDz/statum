# statum

Self-hosted status page. PHP worker pings your URLs every 5 minutes → stores results in SQLite → public page shows uptime bars, response time sparklines, and incident history.

![PHP](https://img.shields.io/badge/PHP-8.3-blue) ![SQLite](https://img.shields.io/badge/storage-SQLite-green) ![Docker](https://img.shields.io/badge/docker-ready-2496ED)

## Quick start

### Docker (recommended)

```bash
cp .env.example .env
# edit .env — set ADMIN_PASSWORD, SITE_NAME
docker compose up -d
```

Opens on `http://localhost:8080`.

### Manual (PHP built-in server)

```bash
cp .env.example .env
php -S localhost:8000 -t public/
```

Add cron entry to ping services:

```
*/5 * * * * php /path/to/statum/worker/check.php >> /dev/null 2>&1
```

## Configuration

Edit `config/services.php`:

```php
return [
    ['name' => 'My API',  'url' => 'https://api.example.com/health'],
    ['name' => 'Website', 'url' => 'https://example.com'],
];
```

Services are synced to the database automatically on the first worker run.

## Environment variables

| Variable         | Default        | Description                        |
|------------------|----------------|------------------------------------|
| `ADMIN_PASSWORD` | `admin`        | Password for `/admin.php`          |
| `SITE_NAME`      | `Status Page`  | Title shown on the public page     |
| `WEBHOOK_TOKEN`  | *(empty)*      | Shared secret for `/api/report`    |
| `ALERT_EMAIL`    | *(empty)*      | Reserved for future email alerts   |

## n8n integration

Import the workflow: `Schedule Trigger → HTTP Request (ping) → IF down → POST /api/report`.

Webhook payload:

```json
{
  "url": "https://api.example.com/health",
  "status_code": 0,
  "latency_ms": 10000
}
```

Add header `X-Webhook-Token: <your WEBHOOK_TOKEN>`.

## Structure

```
config/services.php   service definitions
worker/check.php      cron job (curl + SQLite write)
src/                  Database, ServiceChecker, StatusPage, Env classes
public/               document root (index.php, /api/*, /css/, /js/)
db/status.sqlite      auto-created on first run (gitignored)
```

## Requirements

- PHP 8.3+ with `pdo_sqlite` and `curl` extensions
- Apache with `mod_rewrite` **or** Docker

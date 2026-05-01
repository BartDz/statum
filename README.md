# statum

Self-hosted status page. PHP worker pings your URLs every 5 minutes → stores results in SQLite or Supabase → public page shows uptime bars, response time sparklines, and incident history.

![PHP](https://img.shields.io/badge/PHP-8.3-blue) ![SQLite](https://img.shields.io/badge/storage-SQLite-green) ![Supabase](https://img.shields.io/badge/storage-Supabase-3ECF8E) ![Docker](https://img.shields.io/badge/docker-ready-2496ED)

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

| Variable               | Required | Description                                          |
|------------------------|----------|------------------------------------------------------|
| `ADMIN_PASSWORD`       | **yes**  | Password for `/admin.php` — app refuses to start without it |
| `SITE_NAME`            | no       | Title shown on the public page (default: `Status Page`) |
| `WEBHOOK_TOKEN`        | no       | Shared secret for `/api/report` (disables auth if empty) |
| `CRON_TOKEN`           | **yes**  | Secret for `/cron.php` HTTP trigger — required to use endpoint |
| `ALERT_EMAIL`          | no       | Reserved for future email alerts                     |
| `SUPABASE_URL`         | no       | Supabase project URL — switches storage to PostgreSQL |
| `SUPABASE_KEY`         | no       | Supabase anon key — used for DB badge indicator       |
| `SUPABASE_DB_PASSWORD` | no       | Supabase database password — required together with `SUPABASE_URL` to enable PostgreSQL storage |

## HTTP cron trigger

For hosting environments without shell cron (shared hosting, some PaaS), trigger checks via HTTP:

```
GET /cron.php?token=YOUR_CRON_TOKEN
```

Or with a header:

```bash
curl -H "X-Cron-Token: YOUR_CRON_TOKEN" https://yourdomain.com/cron.php
```

Set `CRON_TOKEN` in `.env`. The endpoint returns JSON with per-service results and writes to `db/worker.log`.

External cron services (cron-job.org, EasyCron, UptimeRobot) can call this URL every 5 minutes.

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

## Supabase integration

When `SUPABASE_URL` and `SUPABASE_DB_PASSWORD` are both set, statum connects directly to Supabase PostgreSQL and stores **all data there** — SQLite is not used. When those variables are absent, statum falls back to local SQLite.

### Setup

1. Create a new Supabase project at [supabase.com](https://supabase.com).
2. Run the following SQL in the Supabase SQL editor to create the required tables:

```sql
CREATE TABLE IF NOT EXISTS services (
    id              bigserial PRIMARY KEY,
    name            text    NOT NULL,
    url             text    NOT NULL,
    expected_status integer NOT NULL DEFAULT 200,
    created_at      timestamptz NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS checks (
    id          bigserial PRIMARY KEY,
    service_id  bigint  NOT NULL REFERENCES services(id),
    status_code integer NOT NULL,
    latency_ms  integer NOT NULL,
    timestamp   timestamptz NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS incidents (
    id          bigserial PRIMARY KEY,
    title       text NOT NULL,
    description text,
    service_id  bigint REFERENCES services(id),
    start_time  timestamptz NOT NULL DEFAULT NOW(),
    end_time    timestamptz,
    status      text NOT NULL DEFAULT 'investigating'
);

CREATE INDEX IF NOT EXISTS idx_checks_service_ts ON checks (service_id, timestamp);
```

3. Find your credentials in **Project Settings**:
   - **Project URL** → `SUPABASE_URL`
   - **API → anon key** → `SUPABASE_KEY` (used for the DB badge indicator)
   - **Database → Database password** → `SUPABASE_DB_PASSWORD`

```
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_DB_PASSWORD=your-db-password
```

statum connects via PDO (native `pdo_pgsql`) — no additional packages required.

## Structure

```
config/services.php   service definitions
worker/check.php      cron job (curl + SQLite write)
src/                  Database, ServiceChecker, StatusPage, Env classes
public/               document root (index.php, /api/*, /css/, /js/)
db/status.sqlite      auto-created on first run (gitignored)
```

## Requirements

- PHP 8.3+ with `pdo_sqlite`, `pdo_pgsql`, and `curl` extensions
- Apache with `mod_rewrite` **or** Docker

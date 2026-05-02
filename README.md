# statum

Self-hosted status page. PHP worker pings your URLs every 5 minutes → stores results in Supabase PostgreSQL → public page shows uptime bars, response time sparklines, and incident history.

![PHP](https://img.shields.io/badge/PHP-8.3-blue) ![Supabase](https://img.shields.io/badge/storage-Supabase-3ECF8E) ![Docker](https://img.shields.io/badge/docker-ready-2496ED)

## Quick start

### Docker (recommended)

```bash
cp .env.example .env
# edit .env — set ADMIN_PASSWORD, SUPABASE_URL, SUPABASE_DB_PASSWORD
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

Services are managed via the admin panel at `/admin.php`. Add each service (name, URL, expected status code) through the UI — no config files needed.

## Environment variables

| Variable               | Required | Description                                          |
|------------------------|----------|------------------------------------------------------|
| `ADMIN_PASSWORD`       | **yes**  | Password for `/admin.php` — app refuses to start without it |
| `SITE_NAME`            | no       | Title shown on the public page (default: `Status Page`) |
| `WEBHOOK_TOKEN`        | no       | Shared secret for `/api/report` (disables auth if empty) |
| `CRON_TOKEN`           | **yes**  | Secret for `/cron.php` HTTP trigger — required to use endpoint |
| `ALERT_EMAIL`          | no       | Reserved for future email alerts                     |
| `SUPABASE_URL`         | **yes**  | Supabase project URL                                 |
| `SUPABASE_KEY`         | **yes**  | Supabase anon key — used for DB badge indicator       |
| `SUPABASE_DB_PASSWORD` | **yes**  | Supabase database password                           |

## HTTP cron trigger

For hosting environments without shell cron (shared hosting, some PaaS), trigger checks via HTTP:

```
GET /cron.php?token=YOUR_CRON_TOKEN
```

Or with a header:

```bash
curl -H "X-Cron-Token: YOUR_CRON_TOKEN" https://yourdomain.com/cron.php
```

Set `CRON_TOKEN` in `.env`. The endpoint returns JSON with per-service results.

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

## Supabase setup

statum requires a Supabase project. All data is stored in Supabase PostgreSQL.

1. Create a new Supabase project at [supabase.com](https://supabase.com).
2. Run the following SQL in the Supabase SQL editor:

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
   - **API → anon key** → `SUPABASE_KEY`
   - **Database → Database password** → `SUPABASE_DB_PASSWORD`

```
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_DB_PASSWORD=your-db-password
```

statum connects via PDO (native `pdo_pgsql`) — no additional packages required.

## Structure

```
worker/check.php      cron job (curl + DB write)
src/                  Database, ServiceChecker, StatusPage, Env classes
public/               document root (index.php, /api/*, /css/, /js/)
```

## Requirements

- PHP 8.3+ with `pdo_pgsql` and `curl` extensions
- Apache with `mod_rewrite` **or** Docker

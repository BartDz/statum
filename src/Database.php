<?php

require_once __DIR__ . '/Entity/Service.php';
require_once __DIR__ . '/Entity/Check.php';
require_once __DIR__ . '/Entity/Incident.php';
require_once __DIR__ . '/Entity/DaySummary.php';

class Database
{
    private PDO    $pdo;
    private string $driver; // 'sqlite' | 'pgsql'

    private function __construct(string $dsn, ?string $user, ?string $pass, string $driver)
    {
        $this->driver = $driver;
        $this->pdo    = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($driver === 'sqlite') {
            $this->migrate();
        }
    }

    public static function fromEnv(): self
    {
        $url      = getenv('SUPABASE_URL');
        $password = getenv('SUPABASE_DB_PASSWORD');

        if ($url && $password) {
            preg_match('/https:\/\/([^.]+)\.supabase\.co/', $url, $m);
            if (empty($m[1])) {
                throw new RuntimeException('Cannot parse project ref from SUPABASE_URL');
            }
            $dsn = sprintf(
                'pgsql:host=db.%s.supabase.co;port=5432;dbname=postgres;sslmode=require',
                $m[1]
            );
            return new self($dsn, 'postgres', $password, 'pgsql');
        }

        return self::sqlite();
    }

    public static function sqlite(): self
    {
        $path = dirname(__DIR__) . '/db/status.sqlite';
        return new self("sqlite:{$path}", null, null, 'sqlite');
    }

    public function isPostgres(): bool
    {
        return $this->driver === 'pgsql';
    }

    // --- internal helpers ---

    private function since(int $days): string
    {
        return $this->driver === 'pgsql'
            ? "NOW() - INTERVAL '{$days} days'"
            : "datetime('now', '-{$days} days')";
    }

    private function migrate(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS services (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                name            TEXT    NOT NULL,
                url             TEXT    NOT NULL,
                expected_status INTEGER NOT NULL DEFAULT 200,
                created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
            );
            CREATE TABLE IF NOT EXISTS checks (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                service_id  INTEGER NOT NULL REFERENCES services(id),
                status_code INTEGER NOT NULL,
                latency_ms  INTEGER NOT NULL,
                timestamp   TEXT    NOT NULL DEFAULT (datetime('now'))
            );
            CREATE TABLE IF NOT EXISTS incidents (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                title       TEXT    NOT NULL,
                description TEXT,
                service_id  INTEGER REFERENCES services(id),
                start_time  TEXT    NOT NULL DEFAULT (datetime('now')),
                end_time    TEXT,
                status      TEXT    NOT NULL DEFAULT 'investigating'
            );
            CREATE INDEX IF NOT EXISTS idx_checks_service_ts ON checks (service_id, timestamp);
        ");
    }

    // --- services ---

    /** @return Service[] */
    public function getServices(): array
    {
        $rows = $this->pdo->query("SELECT * FROM services ORDER BY id")->fetchAll();
        return array_map(fn(array $r) => Service::fromRow($r), $rows);
    }

    public function upsertServicesFromConfig(array $config): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO services (name, url, expected_status)
             SELECT :name, :url, :expected_status
             WHERE NOT EXISTS (SELECT 1 FROM services WHERE url = :url)"
        );
        foreach ($config as $s) {
            $stmt->execute([
                ':name'            => $s['name'],
                ':url'             => $s['url'],
                ':expected_status' => $s['expected_status'] ?? 200,
            ]);
        }
    }

    public function addService(string $name, string $url, int $expectedStatus = 200): void
    {
        $this->pdo->prepare("INSERT INTO services (name, url, expected_status) VALUES (?, ?, ?)")
            ->execute([$name, $url, $expectedStatus]);
    }

    public function deleteService(int $id): void
    {
        $this->pdo->prepare("DELETE FROM incidents WHERE service_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM checks    WHERE service_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM services  WHERE id = ?")->execute([$id]);
    }

    // --- checks ---

    public function recordCheck(int $serviceId, int $statusCode, int $latencyMs): void
    {
        $this->pdo->prepare("INSERT INTO checks (service_id, status_code, latency_ms) VALUES (?, ?, ?)")
            ->execute([$serviceId, $statusCode, $latencyMs]);
    }

    /** @return DaySummary[] */
    public function getDailyUptime(int $serviceId, int $days = 90): array
    {
        $since = $this->since($days);
        $stmt  = $this->pdo->prepare("
            SELECT
                DATE(timestamp) AS date,
                COUNT(*)        AS total,
                SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) AS up_count,
                ROUND(
                    100.0 * SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) / COUNT(*),
                    1
                ) AS uptime_pct
            FROM checks
            WHERE service_id = :id
              AND timestamp >= {$since}
            GROUP BY DATE(timestamp)
            ORDER BY DATE(timestamp)
        ");
        $stmt->execute([':id' => $serviceId]);
        return array_map(fn(array $r) => DaySummary::fromRow($r), $stmt->fetchAll());
    }

    public function getLatestCheck(int $serviceId): ?Check
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM checks WHERE service_id = ? ORDER BY timestamp DESC LIMIT 1"
        );
        $stmt->execute([$serviceId]);
        $row = $stmt->fetch();
        return $row ? Check::fromRow($row) : null;
    }

    /** @return Check[] */
    public function getRecentChecks(int $serviceId, int $hours = 24): array
    {
        $since = $this->driver === 'pgsql'
            ? "NOW() - INTERVAL '{$hours} hours'"
            : "datetime('now', '-{$hours} hours')";

        $stmt = $this->pdo->prepare("
            SELECT * FROM checks
            WHERE service_id = :id
              AND timestamp >= {$since}
            ORDER BY timestamp
        ");
        $stmt->execute([':id' => $serviceId]);
        return array_map(fn(array $r) => Check::fromRow($r), $stmt->fetchAll());
    }

    public function getUptimePercent(int $serviceId, int $days = 30): float
    {
        $since = $this->since($days);
        $stmt  = $this->pdo->prepare("
            SELECT ROUND(
                100.0 * SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                2
            ) AS pct
            FROM checks
            WHERE service_id = :id
              AND timestamp >= {$since}
        ");
        $stmt->execute([':id' => $serviceId]);
        return (float) ($stmt->fetchColumn() ?? 100.0);
    }

    // --- incidents ---

    /** @return Incident[] */
    public function getOpenIncidents(): array
    {
        $rows = $this->pdo->query("
            SELECT i.*, s.name AS service_name
            FROM incidents i
            LEFT JOIN services s ON s.id = i.service_id
            WHERE i.end_time IS NULL
            ORDER BY i.start_time DESC
        ")->fetchAll();
        return array_map(fn(array $r) => Incident::fromRow($r), $rows);
    }

    /** @return Incident[] */
    public function getAllIncidents(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.name AS service_name
            FROM incidents i
            LEFT JOIN services s ON s.id = i.service_id
            ORDER BY i.start_time DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return array_map(fn(array $r) => Incident::fromRow($r), $stmt->fetchAll());
    }

    public function addIncident(string $title, string $description, ?int $serviceId): int
    {
        if ($this->driver === 'pgsql') {
            $stmt = $this->pdo->prepare(
                "INSERT INTO incidents (title, description, service_id) VALUES (?, ?, ?) RETURNING id"
            );
            $stmt->execute([$title, $description, $serviceId]);
            return (int) $stmt->fetchColumn();
        }

        $this->pdo->prepare("INSERT INTO incidents (title, description, service_id) VALUES (?, ?, ?)")
            ->execute([$title, $description, $serviceId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function resolveIncident(int $id): void
    {
        $now = $this->driver === 'pgsql' ? 'NOW()' : "datetime('now')";
        $this->pdo->prepare(
            "UPDATE incidents SET end_time = {$now}, status = 'resolved' WHERE id = ?"
        )->execute([$id]);
    }
}

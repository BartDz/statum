<?php

class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $this->pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ]);
        $this->migrate();
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

    public function getServices(): array
    {
        return $this->pdo->query("SELECT * FROM services ORDER BY id")->fetchAll();
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

    // --- checks ---

    public function recordCheck(int $serviceId, int $statusCode, int $latencyMs): void
    {
        $this->pdo->prepare("INSERT INTO checks (service_id, status_code, latency_ms) VALUES (?, ?, ?)")
                  ->execute([$serviceId, $statusCode, $latencyMs]);
    }

    /** One row per day for the last $days days. */
    public function getDailyUptime(int $serviceId, int $days = 90): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                date(timestamp) AS date,
                COUNT(*)        AS total,
                SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) AS up_count,
                ROUND(
                    100.0 * SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) / COUNT(*),
                    1
                ) AS uptime_pct
            FROM checks
            WHERE service_id = :id
              AND timestamp >= datetime('now', :offset)
            GROUP BY date(timestamp)
            ORDER BY date(timestamp)
        ");
        $stmt->execute([':id' => $serviceId, ':offset' => "-{$days} days"]);
        return $stmt->fetchAll();
    }

    public function getLatestCheck(int $serviceId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM checks WHERE service_id = ? ORDER BY timestamp DESC LIMIT 1"
        );
        $stmt->execute([$serviceId]);
        return $stmt->fetch() ?: null;
    }

    /** Last $hours hours of checks for sparkline. */
    public function getRecentChecks(int $serviceId, int $hours = 24): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM checks
            WHERE service_id = :id
              AND timestamp >= datetime('now', :offset)
            ORDER BY timestamp
        ");
        $stmt->execute([':id' => $serviceId, ':offset' => "-{$hours} hours"]);
        return $stmt->fetchAll();
    }

    public function getUptimePercent(int $serviceId, int $days = 30): float
    {
        $stmt = $this->pdo->prepare("
            SELECT ROUND(
                100.0 * SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) / COUNT(*),
                2
            ) AS pct
            FROM checks
            WHERE service_id = :id
              AND timestamp >= datetime('now', :offset)
        ");
        $stmt->execute([':id' => $serviceId, ':offset' => "-{$days} days"]);
        return (float) ($stmt->fetchColumn() ?? 100.0);
    }

    // --- incidents ---

    public function getOpenIncidents(): array
    {
        return $this->pdo->query("
            SELECT i.*, s.name AS service_name
            FROM incidents i
            LEFT JOIN services s ON s.id = i.service_id
            WHERE i.end_time IS NULL
            ORDER BY i.start_time DESC
        ")->fetchAll();
    }

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
        return $stmt->fetchAll();
    }

    public function addIncident(string $title, string $description, ?int $serviceId): int
    {
        $this->pdo->prepare("INSERT INTO incidents (title, description, service_id) VALUES (?, ?, ?)")
                  ->execute([$title, $description, $serviceId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function resolveIncident(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE incidents SET end_time = datetime('now'), status = 'resolved' WHERE id = ?"
        )->execute([$id]);
    }
}

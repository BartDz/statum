<?php

require_once __DIR__ . '/Entity/Service.php';
require_once __DIR__ . '/Entity/Check.php';
require_once __DIR__ . '/Entity/Incident.php';
require_once __DIR__ . '/Entity/DaySummary.php';

class Database
{
    private PDO $pdo;

    private function __construct(string $dsn, string $user, string $pass)
    {
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function fromEnv(): self
    {
        $url      = getenv('SUPABASE_URL');
        $password = getenv('SUPABASE_DB_PASSWORD');

        if (!$url || !$password) {
            throw new RuntimeException('SUPABASE_URL and SUPABASE_DB_PASSWORD must be set in .env');
        }

        preg_match('/https:\/\/([^.]+)\.supabase\.co/', $url, $m);
        if (empty($m[1])) {
            throw new RuntimeException('Cannot parse project ref from SUPABASE_URL');
        }

        $dsn = sprintf(
            'pgsql:host=db.%s.supabase.co;port=5432;dbname=postgres;sslmode=require',
            $m[1]
        );
        return new self($dsn, 'postgres', $password);
    }

    // --- services ---

    /** @return Service[] */
    public function getServices(): array
    {
        $rows = $this->pdo->query("SELECT * FROM services ORDER BY id")->fetchAll();
        return array_map(fn(array $r) => Service::fromRow($r), $rows);
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
        $stmt = $this->pdo->prepare("
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
              AND timestamp >= NOW() - INTERVAL '{$days} days'
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
        $stmt = $this->pdo->prepare("
            SELECT * FROM checks
            WHERE service_id = :id
              AND timestamp >= NOW() - INTERVAL '{$hours} hours'
            ORDER BY timestamp
        ");
        $stmt->execute([':id' => $serviceId]);
        return array_map(fn(array $r) => Check::fromRow($r), $stmt->fetchAll());
    }

    public function getUptimePercent(int $serviceId, int $days = 30): float
    {
        $stmt = $this->pdo->prepare("
            SELECT ROUND(
                100.0 * SUM(CASE WHEN status_code BETWEEN 200 AND 399 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                2
            ) AS pct
            FROM checks
            WHERE service_id = :id
              AND timestamp >= NOW() - INTERVAL '{$days} days'
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
        $stmt = $this->pdo->prepare(
            "INSERT INTO incidents (title, description, service_id) VALUES (?, ?, ?) RETURNING id"
        );
        $stmt->execute([$title, $description, $serviceId]);
        return (int) $stmt->fetchColumn();
    }

    public function resolveIncident(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE incidents SET end_time = NOW(), status = 'resolved' WHERE id = ?"
        )->execute([$id]);
    }
}

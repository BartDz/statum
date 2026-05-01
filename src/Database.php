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
}

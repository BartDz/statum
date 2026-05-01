<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ServiceChecker.php';

$dbPath  = __DIR__ . '/../db/status.sqlite';
$db      = new Database($dbPath);
$checker = new ServiceChecker();

$config   = require __DIR__ . '/../config/services.php';
$db->upsertServicesFromConfig($config);

$services = $db->getServices();

foreach ($services as $service) {
    $result = $checker->check($service->url);
    $db->recordCheck($service->id, $result['status_code'], $result['latency_ms']);

    $symbol = $result['status_code'] >= 200 && $result['status_code'] < 400 ? '✓' : '✗';
    echo "[{$symbol}] {$service->name} — HTTP {$result['status_code']} ({$result['latency_ms']}ms)\n";
}

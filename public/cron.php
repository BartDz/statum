<?php

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ServiceChecker.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

Env::load(__DIR__ . '/../.env');

$cronToken = getenv('CRON_TOKEN');
if (!$cronToken) {
    http_response_code(500);
    echo json_encode(['error' => 'CRON_TOKEN is not set in .env']);
    exit;
}

$provided = $_SERVER['HTTP_X_CRON_TOKEN'] ?? $_GET['token'] ?? '';
if (!hash_equals($cronToken, $provided)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$logFile = __DIR__ . '/../db/worker.log';
$db      = Database::fromEnv();
$checker = new ServiceChecker();

$results = [];
foreach ($db->getServices() as $service) {
    $result = $checker->check($service->getUrl());
    $db->recordCheck($service->getId(), $result['status_code'], $result['latency_ms']);

    $isUp      = $result['status_code'] >= 200 && $result['status_code'] < 400;
    $results[] = [
        'service'     => $service->getName(),
        'status_code' => $result['status_code'],
        'latency_ms'  => $result['latency_ms'],
        'is_up'       => $isUp,
    ];

    $symbol = $isUp ? '✓' : '✗';
    $line   = "[{$symbol}] {$service->getName()} — HTTP {$result['status_code']} ({$result['latency_ms']}ms) [via HTTP]";
    if (is_writable($logFile) || (!file_exists($logFile) && is_writable(dirname($logFile)))) {
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

echo json_encode(['ok' => true, 'checked' => count($results), 'results' => $results], JSON_PRETTY_PRINT);

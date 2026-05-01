<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ServiceChecker.php';

$dbPath  = __DIR__ . '/../db/status.sqlite';
$logFile = __DIR__ . '/../db/worker.log';

function worker_log(string $msg, string $file): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

try {
    $db      = new Database($dbPath);
    $checker = new ServiceChecker();

    $config = require __DIR__ . '/../config/services.php';
    $db->upsertServicesFromConfig($config);

    $services = $db->getServices();

    foreach ($services as $service) {
        $result = $checker->check($service->url);
        $db->recordCheck($service->id, $result['status_code'], $result['latency_ms']);

        $symbol = $result['status_code'] >= 200 && $result['status_code'] < 400 ? '✓' : '✗';
        $line   = "[{$symbol}] {$service->name} — HTTP {$result['status_code']} ({$result['latency_ms']}ms)";
        echo $line . "\n";
        worker_log($line, $logFile);
    }
} catch (Throwable $e) {
    $msg = 'ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    worker_log($msg, $logFile);
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

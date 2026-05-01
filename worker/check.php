<?php

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ServiceChecker.php';

Env::load(__DIR__ . '/../.env');

$logFile = __DIR__ . '/../db/worker.log';

function worker_log(string $msg, string $file): void
{
    if (is_writable($file) || (!file_exists($file) && is_writable(dirname($file)))) {
        file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

try {
    $db      = Database::fromEnv();
    $checker = new ServiceChecker();

    foreach ($db->getServices() as $service) {
        $result = $checker->check($service->getUrl());
        $db->recordCheck($service->getId(), $result['status_code'], $result['latency_ms']);

        $symbol = $result['status_code'] >= 200 && $result['status_code'] < 400 ? '✓' : '✗';
        $line   = "[{$symbol}] {$service->getName()} — HTTP {$result['status_code']} ({$result['latency_ms']}ms)";
        echo $line . "\n";
        worker_log($line, $logFile);
    }
} catch (Throwable $e) {
    $msg = 'ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    worker_log($msg, $logFile);
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

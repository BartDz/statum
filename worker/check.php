<?php

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ServiceChecker.php';

Env::load(__DIR__ . '/../.env');

try {
    $db      = Database::fromEnv();
    $checker = new ServiceChecker();

    foreach ($db->getServices() as $service) {
        $result = $checker->check($service->getUrl());
        $db->recordCheck($service->getId(), $result['status_code'], $result['latency_ms']);

        $symbol = $result['status_code'] >= 200 && $result['status_code'] < 400 ? '✓' : '✗';
        echo '[' . date('Y-m-d H:i:s') . '] [' . $symbol . '] ' . $service->getName()
            . ' — HTTP ' . $result['status_code'] . ' (' . $result['latency_ms'] . "ms)\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}

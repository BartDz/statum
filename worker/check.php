<?php

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ServiceChecker.php';
require_once __DIR__ . '/../src/Mailer.php';

Env::load(__DIR__ . '/../.env');

try {
    $db      = Database::fromEnv();
    $checker = new ServiceChecker();
    $mailer  = Mailer::fromEnv();

    foreach ($db->getServices() as $service) {
        $prevCheck = $db->getLatestCheck($service->getId());

        $result = $checker->check($service->getUrl());
        $db->recordCheck($service->getId(), $result['status_code'], $result['latency_ms']);

        $isUp   = $result['status_code'] >= 200 && $result['status_code'] < 400;
        $symbol = $isUp ? '✓' : '✗';

        echo '[' . date('Y-m-d H:i:s') . '] [' . $symbol . '] ' . $service->getName()
            . ' — HTTP ' . $result['status_code'] . ' (' . $result['latency_ms'] . "ms)\n";

        if ($mailer === null || $prevCheck === null) {
            continue;
        }

        $wasUp = $prevCheck->isUp();

        if ($wasUp && !$isUp) {
            try {
                $mailer->sendDownAlert(
                    $service->getName(),
                    $service->getUrl(),
                    $result['status_code'],
                    $result['latency_ms']
                );
                echo '[' . date('Y-m-d H:i:s') . '] [ALERT] down alert sent — ' . $service->getName() . "\n";
            } catch (Throwable $e) {
                fwrite(STDERR, 'MAILER ERROR: ' . $e->getMessage() . "\n");
            }
        } elseif (!$wasUp && $isUp) {
            try {
                $mailer->sendUpAlert(
                    $service->getName(),
                    $service->getUrl(),
                    $result['status_code'],
                    $result['latency_ms']
                );
                echo '[' . date('Y-m-d H:i:s') . '] [ALERT] up alert sent — ' . $service->getName() . "\n";
            } catch (Throwable $e) {
                fwrite(STDERR, 'MAILER ERROR: ' . $e->getMessage() . "\n");
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}

<?php

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/StatusPage.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$db   = Database::fromEnv();
$page = new StatusPage($db);
$data = $page->getData();

$services = array_map(fn($row) => [
    'id'         => $row['service']->getId(),
    'name'       => $row['service']->getName(),
    'url'        => $row['service']->getUrl(),
    'is_up'      => $row['is_up'],
    'status'     => $row['latest'] ? $row['latest']->getStatusCode() : null,
    'latency'    => $row['latest'] ? $row['latest']->getLatencyMs() : null,
    'uptime30'   => $row['uptime30'],
    'uptime90'   => $row['uptime90'],
    'checked_at' => $row['latest'] ? $row['latest']->getTimestamp() : null,
], $data);

echo json_encode([
    'overall'   => $page->overallStatus($data),
    'services'  => $services,
    'timestamp' => date('c'),
], JSON_UNESCAPED_SLASHES);

<?php

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/StatusPage.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$db   = new Database(__DIR__ . '/../../db/status.sqlite');
$page = new StatusPage($db);
$data = $page->getData();

$services = array_map(fn($row) => [
    'id'        => $row['service']->id,
    'name'      => $row['service']->name,
    'url'       => $row['service']->url,
    'is_up'     => $row['is_up'],
    'status'    => $row['latest'] ? $row['latest']->status_code : null,
    'latency'   => $row['latest'] ? (int) $row['latest']->latency_ms : null,
    'uptime30'  => $row['uptime30'],
    'uptime90'  => $row['uptime90'],
    'checked_at'=> $row['latest'] ? $row['latest']->timestamp : null,
], $data);

echo json_encode([
    'overall'   => $page->overallStatus($data),
    'services'  => $services,
    'timestamp' => date('c'),
], JSON_UNESCAPED_SLASHES);

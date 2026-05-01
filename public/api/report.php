<?php

require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

// Simple shared-secret auth (set WEBHOOK_TOKEN in .env)
$envFile = __DIR__ . '/../../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($k) . '=' . trim($v));
    }
}

$token = getenv('WEBHOOK_TOKEN');
if ($token) {
    $provided = $_SERVER['HTTP_X_WEBHOOK_TOKEN']
        ?? $_GET['token']
        ?? '';
    if (!hash_equals($token, $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required field: url']);
    exit;
}

$db       = new Database(__DIR__ . '/../../db/status.sqlite');
$services = $db->getServices();

$service = null;
foreach ($services as $s) {
    if ($s->url === $body['url']) {
        $service = $s;
        break;
    }
}

if (!$service) {
    http_response_code(404);
    echo json_encode(['error' => 'Service not found for url: ' . $body['url']]);
    exit;
}

$statusCode = (int) ($body['status_code'] ?? 0);
$latencyMs  = (int) ($body['latency_ms']  ?? 10000);

$db->recordCheck($service->id, $statusCode, $latencyMs);

// Auto-open incident on failure, auto-close on recovery
$isDown      = $statusCode === 0 || $statusCode >= 400;
$openIncidents = $db->getOpenIncidents();
$existing = array_filter($openIncidents, fn($i) => $i->service_id == $service->id);

if ($isDown && empty($existing)) {
    $db->addIncident(
        $service->name . ' is down',
        'Detected by n8n monitor. HTTP ' . ($statusCode ?: 'timeout'),
        $service->id
    );
} elseif (!$isDown && !empty($existing)) {
    foreach ($existing as $inc) {
        $db->resolveIncident($inc->id);
    }
}

echo json_encode(['ok' => true, 'service' => $service->name, 'status_code' => $statusCode]);

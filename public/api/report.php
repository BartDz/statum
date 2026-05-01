<?php

require_once __DIR__ . '/../../src/Env.php';
require_once __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json');

Env::load(__DIR__ . '/../../.env');

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

$db       = Database::fromEnv();
$services = $db->getServices();

$service = null;
foreach ($services as $s) {
    if ($s->getUrl() === $body['url']) {
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

$db->recordCheck($service->getId(), $statusCode, $latencyMs);

// Auto-open incident on failure, auto-close on recovery
$isDown        = $statusCode === 0 || $statusCode >= 400;
$openIncidents = $db->getOpenIncidents();
$existing      = array_filter($openIncidents, fn($i) => $i->getServiceId() === $service->getId());

if ($isDown && empty($existing)) {
    $db->addIncident(
        $service->getName() . ' is down',
        'Detected by n8n monitor. HTTP ' . ($statusCode ?: 'timeout'),
        $service->getId()
    );
} elseif (!$isDown && !empty($existing)) {
    foreach ($existing as $inc) {
        $db->resolveIncident($inc->getId());
    }
}

echo json_encode(['ok' => true, 'service' => $service->getName(), 'status_code' => $statusCode]);

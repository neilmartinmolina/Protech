<?php
declare(strict_types=1);

// Standalone webhook health/receiver endpoint.
// Keep this independent from app.php so webhook calls cannot fail because of
// sessions, auth redirects, schema migrations, or database connectivity.

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($method === 'OPTIONS') {
    header('Allow: GET, POST, HEAD, OPTIONS');
    http_response_code(204);
    exit;
}

if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
    header('Allow: GET, POST, HEAD, OPTIONS');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = file_get_contents('php://input');
$entry = [
    'received_at' => gmdate('c'),
    'method' => $method,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'payload_sha256' => $payload !== false ? hash('sha256', $payload) : null,
    'payload_length' => $payload !== false ? strlen($payload) : 0,
];

$logDir = __DIR__ . '/uploads';
if (is_dir($logDir) && is_writable($logDir)) {
    error_log(json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logDir . '/webhook.log');
}

http_response_code(200);

if ($method !== 'HEAD') {
    echo json_encode([
        'ok' => true,
        'message' => 'Webhook endpoint is reachable.',
        'received_at' => $entry['received_at'],
    ]);
}

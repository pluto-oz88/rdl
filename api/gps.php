<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function fail(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function sessionName(mixed $value): string {
    $session = strtolower(trim((string)$value));
    if ($session === '' || !preg_match('/^[a-z0-9_-]{3,32}$/', $session)) {
        fail('Session must be 3-32 characters using letters, numbers, dash or underscore.');
    }
    return $session;
}

$storageDir = dirname(__DIR__) . '/data';
if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    fail('Could not create GPS storage directory.', 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) fail('Invalid JSON body.');

    $session = sessionName($input['session'] ?? '');
    $lat = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        fail('Invalid latitude or longitude.');
    }

    $heading = isset($input['heading']) && is_numeric($input['heading']) ? (float)$input['heading'] : null;
    $speed = isset($input['speed']) && is_numeric($input['speed']) ? (float)$input['speed'] : null;
    $accuracy = isset($input['accuracy']) && is_numeric($input['accuracy']) ? (float)$input['accuracy'] : null;
    if ($heading !== null && ($heading < 0 || $heading > 360)) $heading = null;

    $payload = [
        'session' => $session,
        'latitude' => (float)$lat,
        'longitude' => (float)$lng,
        'heading' => $heading,
        'speed' => $speed,
        'accuracy' => $accuracy,
        'timestamp' => (int)round(microtime(true) * 1000),
        'receivedAt' => gmdate('c'),
    ];

    $path = $storageDir . '/gps-' . $session . '.json';
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
        fail('Could not save GPS update.', 500);
    }

    echo json_encode(['ok' => true, 'timestamp' => $payload['timestamp']], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $session = sessionName($_GET['session'] ?? '');
    $path = $storageDir . '/gps-' . $session . '.json';
    if (!is_file($path)) fail('No GPS data has been received for this session yet.', 404);

    $raw = file_get_contents($path);
    $payload = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($payload)) fail('Stored GPS data is unavailable.', 500);

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

fail('Method not allowed.', 405);

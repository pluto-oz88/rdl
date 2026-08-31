<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) respond(400, ['error' => 'Invalid JSON request.']);

$query = trim((string)($body['destination'] ?? ''));
if ($query === '') respond(400, ['error' => 'Enter a journey destination.']);

$apiKey = getenv('GOOGLE_PLACES_API_KEY') ?: '';
$localConfig = dirname(__DIR__) . '/config.local.php';
if ($apiKey === '' && is_file($localConfig)) {
    $config = require $localConfig;
    if (is_array($config)) $apiKey = trim((string)($config['google_places_api_key'] ?? ''));
}
if ($apiKey === '') respond(500, ['error' => 'Google Places API key is not configured.']);

$request = [
    'textQuery' => $query,
    'maxResultCount' => 1,
];

$ch = curl_init('https://places.googleapis.com/v1/places:searchText');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.location',
    ],
    CURLOPT_POSTFIELDS => json_encode($request),
]);
$response = curl_exec($ch);
if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    respond(502, ['error' => 'Destination lookup failed: ' . $error]);
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($response, true);
if ($status < 200 || $status >= 300) {
    respond(502, ['error' => $data['error']['message'] ?? 'Google Places destination lookup failed.']);
}
$place = $data['places'][0] ?? null;
$location = $place['location'] ?? null;
if (!is_array($place) || !is_array($location) || !isset($location['latitude'], $location['longitude'])) {
    respond(404, ['error' => 'Destination could not be found. Try a more specific place or address.']);
}

respond(200, [
    'id' => (string)($place['id'] ?? ''),
    'name' => (string)($place['displayName']['text'] ?? $query),
    'address' => (string)($place['formattedAddress'] ?? ''),
    'latitude' => (float)$location['latitude'],
    'longitude' => (float)$location['longitude'],
]);

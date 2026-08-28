<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    respond(400, ['error' => 'Invalid JSON request.']);
}

$lat = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$radiusKm = filter_var($body['radiusKm'] ?? 15, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false || $radiusKm === false) {
    respond(400, ['error' => 'Latitude, longitude and radius are required.']);
}

$radiusKm = max(1, min(50, (float)$radiusKm));

$apiKey = getenv('GOOGLE_PLACES_API_KEY') ?: '';
$localConfig = dirname(__DIR__) . '/config.local.php';
if ($apiKey === '' && is_file($localConfig)) {
    $config = require $localConfig;
    if (is_array($config)) {
        $apiKey = trim((string)($config['google_places_api_key'] ?? ''));
    }
}

if ($apiKey === '') {
    respond(500, [
        'error' => 'Google Places API key not configured. Create config.local.php from config.example.php or set GOOGLE_PLACES_API_KEY.'
    ]);
}

$request = [
    'maxResultCount' => 20,
    'rankPreference' => 'POPULARITY',
    'locationRestriction' => [
        'circle' => [
            'center' => ['latitude' => (float)$lat, 'longitude' => (float)$lng],
            'radius' => $radiusKm * 1000,
        ],
    ],
];

$ch = curl_init('https://places.googleapis.com/v1/places:searchNearby');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: places.id,places.displayName,places.primaryType,places.types,places.location,places.rating,places.userRatingCount,places.formattedAddress,places.photos',
    ],
    CURLOPT_POSTFIELDS => json_encode($request),
]);

$response = curl_exec($ch);
if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    respond(502, ['error' => 'Google Places request failed: ' . $error]);
}

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($response, true);

if ($status < 200 || $status >= 300) {
    $message = $data['error']['message'] ?? 'Google Places returned HTTP ' . $status;
    respond(502, ['error' => $message, 'googleStatus' => $status]);
}

$places = [];
foreach (($data['places'] ?? []) as $place) {
    $location = $place['location'] ?? null;
    if (!is_array($location) || !isset($location['latitude'], $location['longitude'])) {
        continue;
    }
    $places[] = [
        'id' => (string)($place['id'] ?? uniqid('poi-', true)),
        'name' => (string)($place['displayName']['text'] ?? 'Unnamed place'),
        'primaryType' => (string)($place['primaryType'] ?? ''),
        'types' => array_values($place['types'] ?? []),
        'location' => ['lat' => (float)$location['latitude'], 'lng' => (float)$location['longitude']],
        'rating' => isset($place['rating']) ? (float)$place['rating'] : null,
        'userRatingCount' => isset($place['userRatingCount']) ? (int)$place['userRatingCount'] : 0,
        'address' => (string)($place['formattedAddress'] ?? ''),
        'photos' => array_map(static fn(array $photo): array => [
            'name' => $photo['name'] ?? null,
            'widthPx' => $photo['widthPx'] ?? null,
            'heightPx' => $photo['heightPx'] ?? null,
            'authorAttributions' => $photo['authorAttributions'] ?? [],
        ], array_slice($place['photos'] ?? [], 0, 3)),
    ];
}

respond(200, ['places' => $places, 'radiusKm' => $radiusKm]);

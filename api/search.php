<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function googlePlacesRequest(string $apiKey, array $request): array {
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

    return $data['places'] ?? [];
}

function normalizePlace(array $place): ?array {
    $location = $place['location'] ?? null;
    if (!is_array($location) || !isset($location['latitude'], $location['longitude'])) return null;

    return [
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
        'queryInterests' => [],
    ];
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) respond(400, ['error' => 'Invalid JSON request.']);

$lat = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$radiusKm = filter_var($body['radiusKm'] ?? 15, FILTER_VALIDATE_FLOAT);
$interests = is_array($body['interests'] ?? null) ? $body['interests'] : [];
if ($lat === false || $lng === false || $radiusKm === false) respond(400, ['error' => 'Latitude, longitude and radius are required.']);
$radiusKm = max(1, min(50, (float)$radiusKm));

$apiKey = getenv('GOOGLE_PLACES_API_KEY') ?: '';
$localConfig = dirname(__DIR__) . '/config.local.php';
if ($apiKey === '' && is_file($localConfig)) {
    $config = require $localConfig;
    if (is_array($config)) $apiKey = trim((string)($config['google_places_api_key'] ?? ''));
}
if ($apiKey === '') respond(500, ['error' => 'Google Places API key not configured. Create config.local.php from config.example.php or set GOOGLE_PLACES_API_KEY.']);

$typeMap = [
    'history' => ['historical_place', 'historical_landmark', 'cultural_landmark', 'monument', 'castle', 'cemetery'],
    'churches' => ['church', 'buddhist_temple', 'hindu_temple', 'mosque', 'shinto_shrine', 'synagogue'],
    'nature' => ['national_park', 'park', 'city_park', 'hiking_area', 'nature_preserve', 'scenic_spot', 'mountain_peak', 'lake', 'river', 'woods'],
    'gardens' => ['botanical_garden', 'garden'],
    'architecture' => ['cultural_landmark', 'historical_landmark', 'castle', 'monument', 'observation_deck', 'sculpture'],
    'museums' => ['museum', 'history_museum', 'art_museum', 'art_gallery', 'cultural_center', 'tourist_information_center'],
    'coast' => ['beach', 'marina', 'scenic_spot'],
    'wildlife' => ['zoo', 'aquarium', 'wildlife_park', 'wildlife_refuge', 'nature_preserve'],
    'engineering' => ['observation_deck', 'tourist_attraction'],
    'accommodation' => ['hotel', 'resort_hotel', 'motel', 'hostel', 'lodging', 'guest_house', 'bed_and_breakfast'],
    'retail' => ['hardware_store', 'shopping_mall', 'department_store', 'store', 'supermarket', 'grocery_store', 'home_improvement_store', 'warehouse_store'],
    'food' => ['restaurant', 'cafe', 'coffee_shop', 'bakery', 'bar'],
    'business' => ['corporate_office', 'business_center', 'real_estate_agency', 'travel_agency'],
];

$enabledInterests = [];
foreach ($interests as $key => $level) {
    if ($level !== 'off' && isset($typeMap[$key])) $enabledInterests[$key] = $typeMap[$key];
}
if (!$enabledInterests) respond(200, ['places' => [], 'radiusKm' => $radiusKm, 'queriedTypes' => [], 'queriedInterests' => []]);

$placesById = [];
$queriedTypes = [];
foreach ($enabledInterests as $interestKey => $types) {
    $queriedTypes = array_merge($queriedTypes, $types);
    $request = [
        'includedTypes' => array_values(array_unique($types)),
        'maxResultCount' => 20,
        'rankPreference' => 'POPULARITY',
        'locationRestriction' => [
            'circle' => [
                'center' => ['latitude' => (float)$lat, 'longitude' => (float)$lng],
                'radius' => $radiusKm * 1000,
            ],
        ],
    ];

    foreach (googlePlacesRequest($apiKey, $request) as $place) {
        $normalized = normalizePlace($place);
        if ($normalized === null) continue;
        $id = $normalized['id'];
        if (!isset($placesById[$id])) $placesById[$id] = $normalized;
        if (!in_array($interestKey, $placesById[$id]['queryInterests'], true)) {
            $placesById[$id]['queryInterests'][] = $interestKey;
        }
    }
}

respond(200, [
    'places' => array_values($placesById),
    'radiusKm' => $radiusKm,
    'queriedTypes' => array_values(array_unique($queriedTypes)),
    'queriedInterests' => array_keys($enabledInterests),
    'queryBatches' => count($enabledInterests),
]);

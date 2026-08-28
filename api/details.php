<?php
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$placeId = trim((string)($_GET['id'] ?? ''));
if ($placeId === '') {
    respond(400, ['error' => 'Place id is required.']);
}

$apiKey = getenv('GOOGLE_PLACES_API_KEY') ?: '';
$localConfig = dirname(__DIR__) . '/config.local.php';
if ($apiKey === '' && is_file($localConfig)) {
    $config = require $localConfig;
    if (is_array($config)) {
        $apiKey = trim((string)($config['google_places_api_key'] ?? ''));
    }
}

if ($apiKey === '') {
    respond(500, ['error' => 'Google Places API key not configured.']);
}

$url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: id,displayName,formattedAddress,primaryType,types,rating,userRatingCount,websiteUri,nationalPhoneNumber,internationalPhoneNumber,regularOpeningHours,editorialSummary,generativeSummary,reviewSummary,googleMapsUri,photos',
    ],
]);

$response = curl_exec($ch);
if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    respond(502, ['error' => 'Google Places detail request failed: ' . $error]);
}

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($response, true);

if ($status < 200 || $status >= 300) {
    $message = $data['error']['message'] ?? 'Google Places returned HTTP ' . $status;
    respond(502, ['error' => $message, 'googleStatus' => $status]);
}

$hours = array_values($data['regularOpeningHours']['weekdayDescriptions'] ?? $data['regularOpeningHours']['weekdayText'] ?? []);
$summary = trim((string)($data['generativeSummary']['overview']['text'] ?? $data['generativeSummary']['overview'] ?? $data['editorialSummary']['text'] ?? ''));
$reviewSummary = trim((string)($data['reviewSummary']['text']['text'] ?? $data['reviewSummary']['text'] ?? ''));

respond(200, [
    'id' => (string)($data['id'] ?? $placeId),
    'name' => (string)($data['displayName']['text'] ?? 'Unnamed place'),
    'address' => (string)($data['formattedAddress'] ?? ''),
    'primaryType' => (string)($data['primaryType'] ?? ''),
    'types' => array_values($data['types'] ?? []),
    'rating' => isset($data['rating']) ? (float)$data['rating'] : null,
    'userRatingCount' => isset($data['userRatingCount']) ? (int)$data['userRatingCount'] : 0,
    'summary' => $summary,
    'reviewSummary' => $reviewSummary,
    'website' => (string)($data['websiteUri'] ?? ''),
    'phone' => (string)($data['internationalPhoneNumber'] ?? $data['nationalPhoneNumber'] ?? ''),
    'googleMapsUri' => (string)($data['googleMapsUri'] ?? ''),
    'hours' => $hours,
    'photoCount' => count($data['photos'] ?? []),
]);

<?php
/**
 * geocode.php
 * ---------------------------------------------------------------------------
 * AJAX endpoint used by the live-tracking map on form.php. Given a street
 * address (?address=...), calls Google's Geocoding API server-side —
 * keeping GOOGLE_MAPS_SERVER_KEY out of the browser entirely — and returns
 * the real { lat, lng } for it.
 *
 * Response (JSON), success:
 *   { "lat": 25.61, "lng": 85.16, "formatted_address": "..." }
 * Response (JSON), failure:
 *   { "error": "..." }
 * The front-end (initTracking() in form.php) falls back to an approximate
 * pseudo-geocoded point whenever this returns an error, so a bad/partial
 * address never breaks the tracking page.
 * ---------------------------------------------------------------------------
 */
header('Content-Type: application/json');

require_once __DIR__ . '/maps_config.php';

$address = isset($_GET['address']) ? trim((string) $_GET['address']) : '';
if ($address === '') {
    echo json_encode(['error' => 'Missing address']);
    exit;
}

if (GOOGLE_MAPS_SERVER_KEY === 'YOUR_GOOGLE_MAPS_API_KEY_HERE' || GOOGLE_MAPS_SERVER_KEY === '') {
    // Not configured yet — the front-end falls back to an approximate point.
    echo json_encode(['error' => 'Geocoding API key not configured']);
    exit;
}

$url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
    'address' => $address,
    'key'     => GOOGLE_MAPS_SERVER_KEY,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError || $httpCode >= 400) {
    error_log("geocode.php: Geocoding request failed (HTTP {$httpCode}): {$curlError}");
    echo json_encode(['error' => 'Geocoding request failed']);
    exit;
}

$data = json_decode($response, true);
$status = $data['status'] ?? 'UNKNOWN';

if ($status !== 'OK' || empty($data['results'][0]['geometry']['location'])) {
    // Common statuses: ZERO_RESULTS (address not found), REQUEST_DENIED
    // (bad/restricted key), OVER_QUERY_LIMIT (billing not enabled / quota).
    error_log("geocode.php: Geocoding API returned status '{$status}' for address: {$address}");
    echo json_encode(['error' => 'Address not found', 'status' => $status]);
    exit;
}

$loc = $data['results'][0]['geometry']['location'];
echo json_encode([
    'lat' => $loc['lat'],
    'lng' => $loc['lng'],
    'formatted_address' => $data['results'][0]['formatted_address'] ?? $address,
]);

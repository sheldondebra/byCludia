<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (request_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 3) {
    json_response(['ok' => true, 'suggestions' => []]);
}
if (mb_strlen($q) > 120) {
    $q = mb_substr($q, 0, 120);
}

// Soft rate limit: 1 request / second per session (Photon / Nominatim courtesy).
$now = microtime(true);
$last = (float) ($_SESSION['address_suggest_last'] ?? 0);
if ($now - $last < 1.0) {
    usleep((int) ((1.0 - ($now - $last)) * 1_000_000));
}
$_SESSION['address_suggest_last'] = microtime(true);

$url = 'https://photon.komoot.io/api/?' . http_build_query([
    'q' => $q,
    'limit' => 6,
    'lang' => 'en',
]);

$payload = address_suggest_fetch($url);
$features = is_array($payload['features'] ?? null) ? $payload['features'] : [];

// Fallback to Nominatim if Photon is empty or unreachable.
if (!$features) {
    $nomUrl = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $q,
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'limit' => 6,
    ]);
    $nom = address_suggest_fetch($nomUrl);
    if (is_array($nom)) {
        foreach ($nom as $row) {
            if (!is_array($row)) {
                continue;
            }
            $features[] = [
                'properties' => address_suggest_from_nominatim($row),
            ];
        }
    }
}

$suggestions = [];
$seen = [];
foreach ($features as $feature) {
    if (!is_array($feature)) {
        continue;
    }
    $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
    $item = address_suggest_normalize($props);
    if ($item['label'] === '') {
        continue;
    }
    $key = mb_strtolower($item['label']);
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $suggestions[] = $item;
}

json_response(['ok' => true, 'suggestions' => $suggestions]);

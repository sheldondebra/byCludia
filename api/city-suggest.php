<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (request_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$q = trim((string) ($_GET['q'] ?? ''));
$countryName = trim((string) ($_GET['country'] ?? ''));
$countryCode = strtoupper(trim((string) ($_GET['country_code'] ?? '')));
if ($countryCode === '' && $countryName !== '') {
    $countryCode = geo_country_code($countryName);
}

if ($q === '') {
    $popular = geo_popular_cities($countryCode !== '' ? $countryCode : null);
    json_response([
        'ok' => true,
        'suggestions' => array_map(static fn ($name) => ['label' => $name, 'city' => $name], $popular),
    ]);
}

if (mb_strlen($q) > 80) {
    $q = mb_substr($q, 0, 80);
}

$now = microtime(true);
$last = (float) ($_SESSION['address_suggest_last'] ?? 0);
if ($now - $last < 0.8) {
    usleep((int) ((0.8 - ($now - $last)) * 1_000_000));
}
$_SESSION['address_suggest_last'] = microtime(true);

$query = $q . ($countryName !== '' ? (', ' . $countryName) : '');
$url = 'https://photon.komoot.io/api/?q=' . rawurlencode($query)
    . '&limit=8&lang=en'
    . '&osm_tag=place:city&osm_tag=place:town&osm_tag=place:village';

$payload = address_suggest_fetch($url);
$features = is_array($payload['features'] ?? null) ? $payload['features'] : [];

if (!$features) {
    $nomParams = [
        'q' => $q,
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'limit' => 8,
        'featuretype' => 'city',
    ];
    if ($countryCode !== '') {
        $nomParams['countrycodes'] = strtolower($countryCode);
    }
    $nomUrl = 'https://nominatim.openstreetmap.org/search?' . http_build_query($nomParams);
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

foreach (geo_popular_cities($countryCode !== '' ? $countryCode : null) as $city) {
    if (!str_contains(mb_strtolower($city), mb_strtolower($q))) {
        continue;
    }
    $key = mb_strtolower($city);
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $suggestions[] = ['label' => $city, 'city' => $city];
}

foreach ($features as $feature) {
    if (!is_array($feature)) {
        continue;
    }
    $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
    $city = trim((string) (
        $props['name']
        ?? $props['city']
        ?? $props['town']
        ?? $props['village']
        ?? $props['municipality']
        ?? ''
    ));
    if ($city === '') {
        continue;
    }
    $state = trim((string) ($props['state'] ?? ''));
    $country = trim((string) ($props['country'] ?? ''));
    if ($countryCode !== '' && $country !== '') {
        $featureCode = geo_country_code($country);
        if ($featureCode !== '' && $featureCode !== $countryCode) {
            continue;
        }
    }
    $label = $city;
    if ($state !== '' && mb_strtolower($state) !== mb_strtolower($city)) {
        $label .= ', ' . $state;
    }
    if ($country !== '') {
        $label .= ', ' . $country;
    }
    $key = mb_strtolower($city . '|' . $country);
    if (isset($seen[$key]) || isset($seen[mb_strtolower($city)])) {
        continue;
    }
    $seen[$key] = true;
    $suggestions[] = [
        'label' => $label,
        'city' => $city,
        'country' => $country,
    ];
    if (count($suggestions) >= 10) {
        break;
    }
}

json_response(['ok' => true, 'suggestions' => $suggestions]);

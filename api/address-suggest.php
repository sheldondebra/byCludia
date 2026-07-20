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

/**
 * @return array<string, mixed>
 */
function address_suggest_fetch(string $url): array
{
    $ua = 'HairByClaudiaDarlene/1.0 (checkout address autocomplete; contact@byclaudiadarlene.com)';
    $body = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $ua,
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw !== false && $code >= 200 && $code < 300) {
            $body = $raw;
        }
    }

    if ($body === null) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: {$ua}\r\n",
                'timeout' => 6,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false) {
            $body = $raw;
        }
    }

    if ($body === null || $body === '') {
        return [];
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function address_suggest_from_nominatim(array $row): array
{
    $addr = is_array($row['address'] ?? null) ? $row['address'] : [];
    $street = trim((string) ($addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? ''));
    $house = trim((string) ($addr['house_number'] ?? ''));
    $city = trim((string) (
        $addr['city']
        ?? $addr['town']
        ?? $addr['village']
        ?? $addr['municipality']
        ?? $addr['suburb']
        ?? ''
    ));

    return [
        'name' => (string) ($row['name'] ?? ''),
        'street' => $street,
        'housenumber' => $house,
        'city' => $city,
        'postcode' => (string) ($addr['postcode'] ?? ''),
        'country' => (string) ($addr['country'] ?? ''),
        'state' => (string) ($addr['state'] ?? ''),
        'district' => (string) ($addr['city_district'] ?? $addr['suburb'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $props
 * @return array{label: string, address: string, city: string, postcode: string, country: string}
 */
function address_suggest_normalize(array $props): array
{
    $house = trim((string) ($props['housenumber'] ?? ''));
    $street = trim((string) ($props['street'] ?? ''));
    $name = trim((string) ($props['name'] ?? ''));
    $city = trim((string) (
        $props['city']
        ?? $props['town']
        ?? $props['village']
        ?? $props['municipality']
        ?? $props['locality']
        ?? $props['district']
        ?? ''
    ));
    $postcode = trim((string) ($props['postcode'] ?? ''));
    $country = trim((string) ($props['country'] ?? ''));
    $state = trim((string) ($props['state'] ?? ''));

    $line = '';
    if ($street !== '') {
        $line = $house !== '' ? ($house . ' ' . $street) : $street;
    } elseif ($name !== '' && $name !== $city) {
        $line = $name;
    }

    $parts = array_values(array_filter([$line, $postcode, $city, $state !== $city ? $state : '', $country], static fn ($p) => $p !== ''));
    $label = implode(', ', $parts);

    return [
        'label' => $label,
        'address' => $line !== '' ? $line : $label,
        'city' => $city,
        'postcode' => $postcode,
        'country' => $country,
    ];
}

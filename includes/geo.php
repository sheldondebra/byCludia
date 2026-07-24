<?php
declare(strict_types=1);

/**
 * @return list<array{code:string,name:string}>
 */
function geo_countries(): array
{
    static $countries = null;
    if ($countries !== null) {
        return $countries;
    }

    $countries = [
        ['code' => 'AF', 'name' => 'Afghanistan'],
        ['code' => 'AL', 'name' => 'Albania'],
        ['code' => 'DZ', 'name' => 'Algeria'],
        ['code' => 'AD', 'name' => 'Andorra'],
        ['code' => 'AO', 'name' => 'Angola'],
        ['code' => 'AG', 'name' => 'Antigua and Barbuda'],
        ['code' => 'AR', 'name' => 'Argentina'],
        ['code' => 'AM', 'name' => 'Armenia'],
        ['code' => 'AU', 'name' => 'Australia'],
        ['code' => 'AT', 'name' => 'Austria'],
        ['code' => 'AZ', 'name' => 'Azerbaijan'],
        ['code' => 'BS', 'name' => 'Bahamas'],
        ['code' => 'BH', 'name' => 'Bahrain'],
        ['code' => 'BD', 'name' => 'Bangladesh'],
        ['code' => 'BB', 'name' => 'Barbados'],
        ['code' => 'BY', 'name' => 'Belarus'],
        ['code' => 'BE', 'name' => 'Belgium'],
        ['code' => 'BZ', 'name' => 'Belize'],
        ['code' => 'BJ', 'name' => 'Benin'],
        ['code' => 'BT', 'name' => 'Bhutan'],
        ['code' => 'BO', 'name' => 'Bolivia'],
        ['code' => 'BA', 'name' => 'Bosnia and Herzegovina'],
        ['code' => 'BW', 'name' => 'Botswana'],
        ['code' => 'BR', 'name' => 'Brazil'],
        ['code' => 'BN', 'name' => 'Brunei'],
        ['code' => 'BG', 'name' => 'Bulgaria'],
        ['code' => 'BF', 'name' => 'Burkina Faso'],
        ['code' => 'BI', 'name' => 'Burundi'],
        ['code' => 'CV', 'name' => 'Cabo Verde'],
        ['code' => 'KH', 'name' => 'Cambodia'],
        ['code' => 'CM', 'name' => 'Cameroon'],
        ['code' => 'CA', 'name' => 'Canada'],
        ['code' => 'CF', 'name' => 'Central African Republic'],
        ['code' => 'TD', 'name' => 'Chad'],
        ['code' => 'CL', 'name' => 'Chile'],
        ['code' => 'CN', 'name' => 'China'],
        ['code' => 'CO', 'name' => 'Colombia'],
        ['code' => 'KM', 'name' => 'Comoros'],
        ['code' => 'CG', 'name' => 'Congo'],
        ['code' => 'CD', 'name' => 'Congo (DRC)'],
        ['code' => 'CR', 'name' => 'Costa Rica'],
        ['code' => 'CI', 'name' => 'Côte d’Ivoire'],
        ['code' => 'HR', 'name' => 'Croatia'],
        ['code' => 'CU', 'name' => 'Cuba'],
        ['code' => 'CY', 'name' => 'Cyprus'],
        ['code' => 'CZ', 'name' => 'Czechia'],
        ['code' => 'DK', 'name' => 'Denmark'],
        ['code' => 'DJ', 'name' => 'Djibouti'],
        ['code' => 'DM', 'name' => 'Dominica'],
        ['code' => 'DO', 'name' => 'Dominican Republic'],
        ['code' => 'EC', 'name' => 'Ecuador'],
        ['code' => 'EG', 'name' => 'Egypt'],
        ['code' => 'SV', 'name' => 'El Salvador'],
        ['code' => 'GQ', 'name' => 'Equatorial Guinea'],
        ['code' => 'ER', 'name' => 'Eritrea'],
        ['code' => 'EE', 'name' => 'Estonia'],
        ['code' => 'SZ', 'name' => 'Eswatini'],
        ['code' => 'ET', 'name' => 'Ethiopia'],
        ['code' => 'FJ', 'name' => 'Fiji'],
        ['code' => 'FI', 'name' => 'Finland'],
        ['code' => 'FR', 'name' => 'France'],
        ['code' => 'GA', 'name' => 'Gabon'],
        ['code' => 'GM', 'name' => 'Gambia'],
        ['code' => 'GE', 'name' => 'Georgia'],
        ['code' => 'DE', 'name' => 'Germany'],
        ['code' => 'GH', 'name' => 'Ghana'],
        ['code' => 'GR', 'name' => 'Greece'],
        ['code' => 'GD', 'name' => 'Grenada'],
        ['code' => 'GT', 'name' => 'Guatemala'],
        ['code' => 'GN', 'name' => 'Guinea'],
        ['code' => 'GW', 'name' => 'Guinea-Bissau'],
        ['code' => 'GY', 'name' => 'Guyana'],
        ['code' => 'HT', 'name' => 'Haiti'],
        ['code' => 'HN', 'name' => 'Honduras'],
        ['code' => 'HK', 'name' => 'Hong Kong'],
        ['code' => 'HU', 'name' => 'Hungary'],
        ['code' => 'IS', 'name' => 'Iceland'],
        ['code' => 'IN', 'name' => 'India'],
        ['code' => 'ID', 'name' => 'Indonesia'],
        ['code' => 'IR', 'name' => 'Iran'],
        ['code' => 'IQ', 'name' => 'Iraq'],
        ['code' => 'IE', 'name' => 'Ireland'],
        ['code' => 'IL', 'name' => 'Israel'],
        ['code' => 'IT', 'name' => 'Italy'],
        ['code' => 'JM', 'name' => 'Jamaica'],
        ['code' => 'JP', 'name' => 'Japan'],
        ['code' => 'JO', 'name' => 'Jordan'],
        ['code' => 'KZ', 'name' => 'Kazakhstan'],
        ['code' => 'KE', 'name' => 'Kenya'],
        ['code' => 'KI', 'name' => 'Kiribati'],
        ['code' => 'KW', 'name' => 'Kuwait'],
        ['code' => 'KG', 'name' => 'Kyrgyzstan'],
        ['code' => 'LA', 'name' => 'Laos'],
        ['code' => 'LV', 'name' => 'Latvia'],
        ['code' => 'LB', 'name' => 'Lebanon'],
        ['code' => 'LS', 'name' => 'Lesotho'],
        ['code' => 'LR', 'name' => 'Liberia'],
        ['code' => 'LY', 'name' => 'Libya'],
        ['code' => 'LI', 'name' => 'Liechtenstein'],
        ['code' => 'LT', 'name' => 'Lithuania'],
        ['code' => 'LU', 'name' => 'Luxembourg'],
        ['code' => 'MG', 'name' => 'Madagascar'],
        ['code' => 'MW', 'name' => 'Malawi'],
        ['code' => 'MY', 'name' => 'Malaysia'],
        ['code' => 'MV', 'name' => 'Maldives'],
        ['code' => 'ML', 'name' => 'Mali'],
        ['code' => 'MT', 'name' => 'Malta'],
        ['code' => 'MH', 'name' => 'Marshall Islands'],
        ['code' => 'MR', 'name' => 'Mauritania'],
        ['code' => 'MU', 'name' => 'Mauritius'],
        ['code' => 'MX', 'name' => 'Mexico'],
        ['code' => 'FM', 'name' => 'Micronesia'],
        ['code' => 'MD', 'name' => 'Moldova'],
        ['code' => 'MC', 'name' => 'Monaco'],
        ['code' => 'MN', 'name' => 'Mongolia'],
        ['code' => 'ME', 'name' => 'Montenegro'],
        ['code' => 'MA', 'name' => 'Morocco'],
        ['code' => 'MZ', 'name' => 'Mozambique'],
        ['code' => 'MM', 'name' => 'Myanmar'],
        ['code' => 'NA', 'name' => 'Namibia'],
        ['code' => 'NR', 'name' => 'Nauru'],
        ['code' => 'NP', 'name' => 'Nepal'],
        ['code' => 'NL', 'name' => 'Netherlands'],
        ['code' => 'NZ', 'name' => 'New Zealand'],
        ['code' => 'NI', 'name' => 'Nicaragua'],
        ['code' => 'NE', 'name' => 'Niger'],
        ['code' => 'NG', 'name' => 'Nigeria'],
        ['code' => 'KP', 'name' => 'North Korea'],
        ['code' => 'MK', 'name' => 'North Macedonia'],
        ['code' => 'NO', 'name' => 'Norway'],
        ['code' => 'OM', 'name' => 'Oman'],
        ['code' => 'PK', 'name' => 'Pakistan'],
        ['code' => 'PW', 'name' => 'Palau'],
        ['code' => 'PS', 'name' => 'Palestine'],
        ['code' => 'PA', 'name' => 'Panama'],
        ['code' => 'PG', 'name' => 'Papua New Guinea'],
        ['code' => 'PY', 'name' => 'Paraguay'],
        ['code' => 'PE', 'name' => 'Peru'],
        ['code' => 'PH', 'name' => 'Philippines'],
        ['code' => 'PL', 'name' => 'Poland'],
        ['code' => 'PT', 'name' => 'Portugal'],
        ['code' => 'QA', 'name' => 'Qatar'],
        ['code' => 'RO', 'name' => 'Romania'],
        ['code' => 'RU', 'name' => 'Russia'],
        ['code' => 'RW', 'name' => 'Rwanda'],
        ['code' => 'KN', 'name' => 'Saint Kitts and Nevis'],
        ['code' => 'LC', 'name' => 'Saint Lucia'],
        ['code' => 'VC', 'name' => 'Saint Vincent and the Grenadines'],
        ['code' => 'WS', 'name' => 'Samoa'],
        ['code' => 'SM', 'name' => 'San Marino'],
        ['code' => 'ST', 'name' => 'Sao Tome and Principe'],
        ['code' => 'SA', 'name' => 'Saudi Arabia'],
        ['code' => 'SN', 'name' => 'Senegal'],
        ['code' => 'RS', 'name' => 'Serbia'],
        ['code' => 'SC', 'name' => 'Seychelles'],
        ['code' => 'SL', 'name' => 'Sierra Leone'],
        ['code' => 'SG', 'name' => 'Singapore'],
        ['code' => 'SK', 'name' => 'Slovakia'],
        ['code' => 'SI', 'name' => 'Slovenia'],
        ['code' => 'SB', 'name' => 'Solomon Islands'],
        ['code' => 'SO', 'name' => 'Somalia'],
        ['code' => 'ZA', 'name' => 'South Africa'],
        ['code' => 'KR', 'name' => 'South Korea'],
        ['code' => 'SS', 'name' => 'South Sudan'],
        ['code' => 'ES', 'name' => 'Spain'],
        ['code' => 'LK', 'name' => 'Sri Lanka'],
        ['code' => 'SD', 'name' => 'Sudan'],
        ['code' => 'SR', 'name' => 'Suriname'],
        ['code' => 'SE', 'name' => 'Sweden'],
        ['code' => 'CH', 'name' => 'Switzerland'],
        ['code' => 'SY', 'name' => 'Syria'],
        ['code' => 'TW', 'name' => 'Taiwan'],
        ['code' => 'TJ', 'name' => 'Tajikistan'],
        ['code' => 'TZ', 'name' => 'Tanzania'],
        ['code' => 'TH', 'name' => 'Thailand'],
        ['code' => 'TL', 'name' => 'Timor-Leste'],
        ['code' => 'TG', 'name' => 'Togo'],
        ['code' => 'TO', 'name' => 'Tonga'],
        ['code' => 'TT', 'name' => 'Trinidad and Tobago'],
        ['code' => 'TN', 'name' => 'Tunisia'],
        ['code' => 'TR', 'name' => 'Turkey'],
        ['code' => 'TM', 'name' => 'Turkmenistan'],
        ['code' => 'TV', 'name' => 'Tuvalu'],
        ['code' => 'UG', 'name' => 'Uganda'],
        ['code' => 'UA', 'name' => 'Ukraine'],
        ['code' => 'AE', 'name' => 'United Arab Emirates'],
        ['code' => 'GB', 'name' => 'United Kingdom'],
        ['code' => 'US', 'name' => 'United States'],
        ['code' => 'UY', 'name' => 'Uruguay'],
        ['code' => 'UZ', 'name' => 'Uzbekistan'],
        ['code' => 'VU', 'name' => 'Vanuatu'],
        ['code' => 'VA', 'name' => 'Vatican City'],
        ['code' => 'VE', 'name' => 'Venezuela'],
        ['code' => 'VN', 'name' => 'Vietnam'],
        ['code' => 'YE', 'name' => 'Yemen'],
        ['code' => 'ZM', 'name' => 'Zambia'],
        ['code' => 'ZW', 'name' => 'Zimbabwe'],
    ];

    return $countries;
}

function geo_country_code(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '';
    }
    $lower = mb_strtolower($name);
    foreach (geo_countries() as $country) {
        if (mb_strtolower($country['name']) === $lower || mb_strtolower($country['code']) === $lower) {
            return $country['code'];
        }
    }
    return '';
}

/**
 * Popular cities shown instantly when the city field is focused.
 *
 * @return list<string>
 */
function geo_popular_cities(?string $countryCode = null): array
{
    $map = [
        'GB' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Liverpool', 'Bristol', 'Edinburgh', 'Sheffield', 'Cardiff', 'Nottingham', 'Leicester', 'Coventry', 'Belfast', 'Newcastle'],
        'GH' => ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Cape Coast', 'Tema', 'Sunyani', 'Ho', 'Koforidua', 'Wa'],
        'US' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Austin', 'Seattle', 'Denver', 'Boston', 'Miami', 'Atlanta'],
        'NG' => ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Benin City', 'Enugu', 'Abeokuta', 'Jos', 'Ilorin'],
        'CA' => ['Toronto', 'Vancouver', 'Montreal', 'Calgary', 'Ottawa', 'Edmonton', 'Winnipeg', 'Quebec City', 'Hamilton', 'Victoria'],
        'IE' => ['Dublin', 'Cork', 'Galway', 'Limerick', 'Waterford', 'Kilkenny', 'Sligo'],
        'FR' => ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes', 'Bordeaux', 'Lille', 'Strasbourg'],
        'DE' => ['Berlin', 'Munich', 'Hamburg', 'Frankfurt', 'Cologne', 'Stuttgart', 'Düsseldorf', 'Leipzig', 'Dresden'],
        'NL' => ['Amsterdam', 'Rotterdam', 'The Hague', 'Utrecht', 'Eindhoven', 'Groningen'],
        'AU' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Canberra', 'Gold Coast'],
        'ZA' => ['Cape Town', 'Johannesburg', 'Durban', 'Pretoria', 'Port Elizabeth', 'Bloemfontein'],
        'KE' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret'],
        'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah'],
        'IN' => ['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad'],
    ];

    $code = strtoupper(trim((string) $countryCode));
    if ($code !== '' && isset($map[$code])) {
        return $map[$code];
    }

    return ['London', 'Accra', 'New York', 'Lagos', 'Paris', 'Dubai', 'Toronto', 'Berlin', 'Amsterdam', 'Sydney'];
}

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

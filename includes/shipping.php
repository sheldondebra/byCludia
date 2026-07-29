<?php
declare(strict_types=1);

/**
 * Default shipping rate in GBP (applies unless a country override exists).
 */
function shipping_default_rate(): float
{
    return max(0.0, (float) (setting('shipping_flat', '15') ?: 15));
}

/**
 * Free-shipping threshold in GBP (0 = disabled).
 */
function shipping_free_threshold(): float
{
    return max(0.0, (float) (setting('free_shipping_threshold', '') ?: 0));
}

function geo_country_name(?string $code): string
{
    $code = strtoupper(trim((string) $code));
    if ($code === '' || $code === 'OTHER') {
        return 'Other';
    }
    foreach (geo_countries() as $country) {
        if ($country['code'] === $code) {
            return $country['name'];
        }
    }
    return $code;
}

/**
 * Normalize posted country code. Empty / OTHER → null (use default rate).
 */
function shipping_normalize_country_code(?string $code): ?string
{
    $code = strtoupper(trim((string) $code));
    if ($code === '' || $code === 'OTHER' || $code === 'XX') {
        return null;
    }
    if (!preg_match('/^[A-Z]{2}$/', $code)) {
        return null;
    }
    foreach (geo_countries() as $country) {
        if ($country['code'] === $code) {
            return $code;
        }
    }
    return null;
}

/**
 * Resolve shipping for a country + cart subtotal (GBP).
 *
 * @return array{rate: float, source: string, country_code: ?string}
 */
function shipping_rate_for_country(?string $countryCode, float $subtotalGbp): array
{
    $code = shipping_normalize_country_code($countryCode);
    $threshold = shipping_free_threshold();
    if ($threshold > 0 && $subtotalGbp >= $threshold) {
        return ['rate' => 0.0, 'source' => 'free', 'country_code' => $code];
    }

    if ($code !== null) {
        $stmt = db()->prepare(
            'SELECT rate FROM shipping_country_rates WHERE country_code = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'rate' => max(0.0, (float) $row['rate']),
                'source' => 'override',
                'country_code' => $code,
            ];
        }
    }

    return [
        'rate' => shipping_default_rate(),
        'source' => 'default',
        'country_code' => $code,
    ];
}

/**
 * Active overrides as code => rate (GBP) for checkout JS.
 *
 * @return array<string, float>
 */
function shipping_active_override_map(): array
{
    $map = [];
    try {
        $rows = db()->query(
            'SELECT country_code, rate FROM shipping_country_rates WHERE is_active = 1'
        )->fetchAll();
        foreach ($rows as $row) {
            $code = strtoupper((string) $row['country_code']);
            $map[$code] = max(0.0, (float) $row['rate']);
        }
    } catch (Throwable $e) {
        // Table may not exist yet during early boot
    }
    return $map;
}

/**
 * @return list<array>
 */
function shipping_country_rates_all(): array
{
    return db()->query(
        'SELECT * FROM shipping_country_rates ORDER BY country_code ASC'
    )->fetchAll() ?: [];
}

function shipping_country_rate_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM shipping_country_rates WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array{ok: bool, error?: string, id?: int}
 */
function shipping_country_rate_save(int $id, string $countryCode, float $rate, bool $active, string $label = ''): array
{
    $code = shipping_normalize_country_code($countryCode);
    if ($code === null) {
        return ['ok' => false, 'error' => 'Choose a valid country.'];
    }
    if ($rate < 0) {
        return ['ok' => false, 'error' => 'Rate cannot be negative.'];
    }
    $label = trim($label);
    $activeInt = $active ? 1 : 0;

    $dup = db()->prepare('SELECT id FROM shipping_country_rates WHERE country_code = ? AND id <> ?');
    $dup->execute([$code, $id]);
    if ($dup->fetchColumn()) {
        return ['ok' => false, 'error' => 'An override for that country already exists.'];
    }

    if ($id > 0) {
        db()->prepare(
            'UPDATE shipping_country_rates SET country_code = ?, rate = ?, is_active = ?, label = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$code, $rate, $activeInt, $label !== '' ? $label : null, $id]);
        return ['ok' => true, 'id' => $id];
    }

    db()->prepare(
        'INSERT INTO shipping_country_rates (country_code, rate, is_active, label) VALUES (?, ?, ?, ?)'
    )->execute([$code, $rate, $activeInt, $label !== '' ? $label : null]);
    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

/**
 * Upsert one country rate by ISO code (creates or updates existing row).
 *
 * @return array{ok: bool, error?: string, id?: int}
 */
function shipping_country_rate_upsert(string $countryCode, float $rate, bool $active, string $label = ''): array
{
    $code = shipping_normalize_country_code($countryCode);
    if ($code === null) {
        return ['ok' => false, 'error' => 'Choose a valid country.'];
    }
    $existing = db()->prepare('SELECT id FROM shipping_country_rates WHERE country_code = ?');
    $existing->execute([$code]);
    $id = (int) ($existing->fetchColumn() ?: 0);
    return shipping_country_rate_save($id, $code, $rate, $active, $label);
}

/**
 * Apply the same fee to many countries (group assign).
 *
 * @param list<string>|array $countryCodes
 * @return array{ok: bool, error?: string, saved: int}
 */
function shipping_country_rate_save_many(array $countryCodes, float $rate, bool $active, string $label = ''): array
{
    if ($rate < 0) {
        return ['ok' => false, 'error' => 'Rate cannot be negative.', 'saved' => 0];
    }

    $codes = [];
    foreach ($countryCodes as $raw) {
        $code = shipping_normalize_country_code((string) $raw);
        if ($code !== null && !in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }
    if ($codes === []) {
        return ['ok' => false, 'error' => 'Select at least one country.', 'saved' => 0];
    }

    $saved = 0;
    foreach ($codes as $code) {
        $result = shipping_country_rate_upsert($code, $rate, $active, $label);
        if ($result['ok']) {
            $saved++;
        }
    }

    return ['ok' => $saved > 0, 'saved' => $saved, 'error' => $saved > 0 ? null : 'Could not save overrides.'];
}

function shipping_country_rate_delete(int $id): void
{
    if ($id <= 0) {
        return;
    }
    db()->prepare('DELETE FROM shipping_country_rates WHERE id = ?')->execute([$id]);
}

function shipping_country_rate_toggle(int $id): void
{
    if ($id <= 0) {
        return;
    }
    db()->prepare(
        'UPDATE shipping_country_rates SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
    )->execute([$id]);
}

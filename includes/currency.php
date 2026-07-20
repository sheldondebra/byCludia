<?php
declare(strict_types=1);

function currency_rates(): array
{
    static $rates = null;
    if ($rates !== null) {
        return $rates;
    }
    $rates = [];
    try {
        $rows = db()->query('SELECT code, name, symbol, rate_from_gbp FROM currency_rates')->fetchAll();
        foreach ($rows as $row) {
            $rates[$row['code']] = $row;
        }
    } catch (Throwable $e) {
        $rates = [
            'GBP' => ['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'rate_from_gbp' => 1],
            'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate_from_gbp' => 1.27],
            'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate_from_gbp' => 1.17],
            'GHS' => ['code' => 'GHS', 'name' => 'Ghana Cedi', 'symbol' => 'GH₵', 'rate_from_gbp' => 16.5],
        ];
    }
    return $rates;
}

function current_currency(): string
{
    global $config;
    $allowed = $config['currencies'] ?? ['GBP', 'USD', 'EUR', 'GHS'];
    $code = $_SESSION['currency'] ?? ($config['base_currency'] ?? 'GBP');
    return in_array($code, $allowed, true) ? $code : 'GBP';
}

function set_currency(string $code): void
{
    global $config;
    $allowed = $config['currencies'] ?? ['GBP', 'USD', 'EUR', 'GHS'];
    if (in_array($code, $allowed, true)) {
        $_SESSION['currency'] = $code;
    }
}

function convert_price(float $gbpAmount, ?string $to = null): float
{
    $to = $to ?? current_currency();
    $rates = currency_rates();
    $rate = (float) ($rates[$to]['rate_from_gbp'] ?? 1);
    return round($gbpAmount * $rate, 2);
}

function money(float $gbpAmount, ?string $currency = null): string
{
    $currency = $currency ?? current_currency();
    $rates = currency_rates();
    $symbol = $rates[$currency]['symbol'] ?? $currency . ' ';
    $amount = convert_price($gbpAmount, $currency);
    return $symbol . number_format($amount, 2);
}

function currency_symbol(?string $code = null): string
{
    $code = $code ?? current_currency();
    $rates = currency_rates();
    return $rates[$code]['symbol'] ?? $code;
}

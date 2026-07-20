<?php
declare(strict_types=1);

/**
 * SMS sending via SplitSMS (https://www.splitsms.com).
 * Configured via admin Integrations settings.
 */

function sms_enabled(): bool
{
    return setting('sms_enabled', '0') === '1' && setting('splitsms_api_key', '') !== '';
}

/** Normalise a phone number to international format (best-effort, Ghana default). */
function sms_normalize(string $phone): string
{
    $p = preg_replace('/[^0-9+]/', '', $phone);
    if ($p === '') {
        return '';
    }
    if (str_starts_with($p, '+')) {
        return substr($p, 1);
    }
    if (str_starts_with($p, '00')) {
        return substr($p, 2);
    }
    // Local Ghana number starting with 0 -> 233
    if (str_starts_with($p, '0')) {
        return '233' . substr($p, 1);
    }
    return $p;
}

/**
 * Send an SMS. Returns ['ok'=>bool, 'error'=>?string, 'response'=>mixed].
 */
function send_sms(string $to, string $message): array
{
    if (!sms_enabled()) {
        return ['ok' => false, 'error' => 'SMS is disabled or not configured.'];
    }
    $to = sms_normalize($to);
    if ($to === '') {
        return ['ok' => false, 'error' => 'Invalid recipient number.'];
    }

    $apiKey = (string) setting('splitsms_api_key', '');
    $sender = (string) setting('splitsms_sender', 'ClaudiaD');
    $base = rtrim((string) setting('splitsms_base_url', 'https://www.splitsms.com'), '/');

    $payload = json_encode([
        'sender' => $sender,
        'recipients' => [$to],
        'message' => $message,
    ]);

    $ch = curl_init($base . '/api/v1/sms/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'error' => 'Request failed: ' . $err];
    }
    $decoded = json_decode((string) $res, true);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'response' => $decoded ?? $res];
    }
    $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? $res) : $res;
    return ['ok' => false, 'error' => 'HTTP ' . $code . ': ' . (is_string($msg) ? $msg : json_encode($msg))];
}

/** Check SplitSMS wallet balance (for admin test). */
function sms_balance(): array
{
    $apiKey = (string) setting('splitsms_api_key', '');
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'No API key configured.'];
    }
    $base = rtrim((string) setting('splitsms_base_url', 'https://www.splitsms.com'), '/');
    $ch = curl_init($base . '/api/v1/balance');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) {
        return ['ok' => false, 'error' => 'Request failed.'];
    }
    return ['ok' => $code >= 200 && $code < 300, 'response' => json_decode((string) $res, true) ?? $res];
}

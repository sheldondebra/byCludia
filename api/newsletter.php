<?php
/**
 * Alias for newsletter forms — requires phone + email, saves to subscribers.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

if (request_method() !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
}

if (!verify_csrf(post('csrf_token'))) {
    json_response(['ok' => false, 'message' => 'Invalid session'], 403);
}

$name = trim((string) post('name', ''));
$phone = trim((string) post('phone', ''));
$email = strtolower(trim((string) post('email', '')));
$source = trim((string) post('source', 'newsletter')) ?: 'newsletter';

$phoneDigits = preg_replace('/[^0-9+]/', '', $phone) ?? '';
$phoneLen = strlen(preg_replace('/[^0-9]/', '', $phoneDigits) ?? '');

if ($phoneDigits === '' || $phoneLen < 7) {
    json_response(['ok' => false, 'message' => 'Please enter a valid phone number.'], 422);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
}

try {
    db()->prepare('INSERT INTO subscribers (name, phone, email, source) VALUES (?, ?, ?, ?)')
        ->execute([$name !== '' ? $name : null, $phoneDigits, $email, $source]);
} catch (Throwable $e) {
    try {
        db()->prepare('UPDATE subscribers SET email = COALESCE(NULLIF(email, ""), ?), name = COALESCE(name, ?) WHERE phone = ?')
            ->execute([$email, $name !== '' ? $name : null, $phoneDigits]);
    } catch (Throwable $e2) {
    }
    json_response(['ok' => true, 'message' => "You're already on the list."]);
}

json_response(['ok' => true, 'message' => "Subscribed! We'll keep you posted by text and email."]);

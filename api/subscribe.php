<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

if (request_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed', 'message' => 'Method not allowed'], 405);
}

if (!verify_csrf(post('csrf_token'))) {
    json_response(['ok' => false, 'error' => 'Invalid session. Please refresh and try again.', 'message' => 'Invalid session. Please refresh and try again.'], 403);
}

$name = trim((string) post('name', ''));
$phone = trim((string) post('phone', ''));
$email = strtolower(trim((string) post('email', '')));
$source = trim((string) post('source', 'newsletter')) ?: 'newsletter';

$phoneDigits = preg_replace('/[^0-9+]/', '', $phone) ?? '';
$phoneLen = strlen(preg_replace('/[^0-9]/', '', $phoneDigits) ?? '');

if ($phoneDigits === '' || $phoneLen < 7) {
    json_response(['ok' => false, 'error' => 'Please enter a valid phone number.', 'message' => 'Please enter a valid phone number.'], 422);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please enter a valid email address.', 'message' => 'Please enter a valid email address.'], 422);
}

try {
    $stmt = db()->prepare('INSERT INTO subscribers (name, phone, email, source) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name !== '' ? $name : null, $phoneDigits, $email, $source]);
    $isNew = true;
} catch (Throwable $e) {
    // Duplicate phone (UNIQUE) — treat as already subscribed.
    $isNew = false;
    try {
        db()->prepare('UPDATE subscribers SET email = COALESCE(NULLIF(email, ""), ?), name = COALESCE(name, ?) WHERE phone = ?')
            ->execute([$email, $name !== '' ? $name : null, $phoneDigits]);
    } catch (Throwable $e2) {
        // ignore update failure
    }
}

$discount = trim((string) setting('popup_discount_code', ''));

if ($isNew && function_exists('sms_enabled') && sms_enabled()) {
    $store = setting('store_name', 'By Claudia Darlene');
    $msg = $store . ': Thanks for subscribing!';
    if ($discount !== '') {
        $msg .= ' Use code ' . $discount . ' for a discount on your first order.';
    }
    @send_sms($phoneDigits, $msg);
}

$success = $isNew
    ? (string) setting('popup_success', "You're on the list! We'll keep you posted by text and email.")
    : "You're already on the list — thanks for staying with us.";

json_response([
    'ok' => true,
    'new' => $isNew,
    'message' => $success,
    'discount' => $discount,
]);

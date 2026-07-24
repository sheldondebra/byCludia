<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

if (request_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$code = (string) ($_GET['code'] ?? '');

if (!in_array($type, ['coupon', 'gift'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid type'], 400);
}

if ($type === 'coupon') {
    json_response(coupon_validate($code, cart_subtotal_gbp()));
}

json_response(gift_card_validate($code));

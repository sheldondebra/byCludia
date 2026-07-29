<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

if (request_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!verify_csrf(post('csrf_token'))) {
    json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
}

$productId = (int) post('product_id');
$result = wishlist_toggle($productId);

if (!$result['ok']) {
    json_response($result, 400);
}

json_response($result);

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

$action = (string) post('action', 'add');

$withPreview = static function (array $result): array {
    if (!empty($result['ok'])) {
        $result['preview_html'] = cart_preview_html();
    }
    return $result;
};

if ($action === 'add') {
    $result = cart_add((int) post('product_id'), (int) post('variant_id'), max(1, (int) post('quantity', 1)));
    json_response($withPreview($result), $result['ok'] ? 200 : 400);
}

if ($action === 'add_gift') {
    $result = cart_add_gift(
        (float) post('amount'),
        (string) post('recipient_name', ''),
        (string) post('recipient_email', ''),
        (string) post('sender_name', ''),
        (string) post('message', '')
    );
    json_response($withPreview($result), $result['ok'] ? 200 : 400);
}

if ($action === 'update') {
    $result = cart_update((int) post('item_id'), (int) post('quantity'));
    json_response($withPreview($result), $result['ok'] ? 200 : 400);
}

if ($action === 'remove') {
    $result = cart_remove((int) post('item_id'));
    json_response($withPreview($result));
}

json_response(['ok' => false, 'error' => 'Unknown action'], 400);

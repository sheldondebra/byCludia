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

$user = current_user();
if (!$user) {
    json_response(['ok' => false, 'login_required' => true, 'error' => 'Please sign in to save favourites'], 401);
}

$productId = (int) post('product_id');
if ($productId <= 0) {
    json_response(['ok' => false, 'error' => 'Invalid product'], 400);
}

$exists = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
$exists->execute([$user['id'], $productId]);
$active = (bool) $exists->fetchColumn();

if ($active) {
    db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$user['id'], $productId]);
    $active = false;
} else {
    try {
        db()->prepare('INSERT OR IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
    } catch (Throwable $e) {
        db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
    }
    $active = true;
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
$countStmt->execute([$user['id']]);

json_response(['ok' => true, 'active' => $active, 'count' => (int) $countStmt->fetchColumn()]);

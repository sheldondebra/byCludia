<?php
declare(strict_types=1);

const GUEST_WISHLIST_MAX = 50;

/**
 * Normalized unique product IDs from the guest session wishlist.
 *
 * @return list<int>
 */
function guest_wishlist_ids(): array
{
    $raw = $_SESSION['wishlist'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $id) {
        $id = (int) $id;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function guest_wishlist_set(array $ids): void
{
    $clean = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && !in_array($id, $clean, true)) {
            $clean[] = $id;
        }
        if (count($clean) >= GUEST_WISHLIST_MAX) {
            break;
        }
    }
    $_SESSION['wishlist'] = $clean;
}

function wishlist_count(): int
{
    $user = current_user();
    if ($user) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
        $stmt->execute([(int) $user['id']]);
        return (int) $stmt->fetchColumn();
    }
    return count(guest_wishlist_ids());
}

function wishlist_has(int $productId): bool
{
    if ($productId <= 0) {
        return false;
    }
    $user = current_user();
    if ($user) {
        $stmt = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
        $stmt->execute([(int) $user['id'], $productId]);
        return (bool) $stmt->fetchColumn();
    }
    return in_array($productId, guest_wishlist_ids(), true);
}

/**
 * Toggle a product for the current user or guest session.
 *
 * @return array{ok: bool, active: bool, count: int, error?: string}
 */
function wishlist_toggle(int $productId): array
{
    if ($productId <= 0) {
        return ['ok' => false, 'active' => false, 'count' => wishlist_count(), 'error' => 'Invalid product'];
    }

    $check = db()->prepare('SELECT id FROM products WHERE id = ? AND is_active = 1');
    $check->execute([$productId]);
    if (!$check->fetchColumn()) {
        return ['ok' => false, 'active' => false, 'count' => wishlist_count(), 'error' => 'Product not found'];
    }

    $user = current_user();
    if ($user) {
        $userId = (int) $user['id'];
        $exists = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
        $exists->execute([$userId, $productId]);
        $active = (bool) $exists->fetchColumn();

        if ($active) {
            db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$userId, $productId]);
            $active = false;
        } else {
            try {
                db()->prepare('INSERT OR IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);
            } catch (Throwable $e) {
                db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);
            }
            $active = true;
        }

        return ['ok' => true, 'active' => $active, 'count' => wishlist_count()];
    }

    $ids = guest_wishlist_ids();
    $idx = array_search($productId, $ids, true);
    if ($idx !== false) {
        array_splice($ids, (int) $idx, 1);
        guest_wishlist_set($ids);
        return ['ok' => true, 'active' => false, 'count' => count(guest_wishlist_ids())];
    }

    if (count($ids) >= GUEST_WISHLIST_MAX) {
        return [
            'ok' => false,
            'active' => false,
            'count' => count($ids),
            'error' => 'Wishlist is full. Remove an item or sign in to save more.',
        ];
    }

    $ids[] = $productId;
    guest_wishlist_set($ids);
    return ['ok' => true, 'active' => true, 'count' => count(guest_wishlist_ids())];
}

/**
 * Remove a product from the current wishlist (user DB or guest session).
 */
function wishlist_remove(int $productId): void
{
    if ($productId <= 0) {
        return;
    }
    $user = current_user();
    if ($user) {
        db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')
            ->execute([(int) $user['id'], $productId]);
        return;
    }
    $ids = array_values(array_filter(guest_wishlist_ids(), static fn (int $id): bool => $id !== $productId));
    guest_wishlist_set($ids);
}

/**
 * Products on the current wishlist, newest-first when possible.
 *
 * @return list<array>
 */
function wishlist_products(): array
{
    $user = current_user();
    if ($user) {
        $stmt = db()->prepare(
            'SELECT p.* FROM wishlists w
             JOIN products p ON p.id = w.product_id
             WHERE w.user_id = ? AND p.is_active = 1
             ORDER BY w.id DESC'
        );
        $stmt->execute([(int) $user['id']]);
        return $stmt->fetchAll() ?: [];
    }

    $ids = guest_wishlist_ids();
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll() ?: [];
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $ordered = [];
    foreach (array_reverse($ids) as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return $ordered;
}

/**
 * Merge guest session wishlist into a user account, then clear the session list.
 */
function wishlist_merge_session_into_user(int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $ids = guest_wishlist_ids();
    if ($ids === []) {
        return;
    }

    foreach ($ids as $productId) {
        $check = db()->prepare('SELECT 1 FROM products WHERE id = ? AND is_active = 1');
        $check->execute([$productId]);
        if (!$check->fetchColumn()) {
            continue;
        }
        try {
            db()->prepare('INSERT OR IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);
        } catch (Throwable $e) {
            try {
                db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$userId, $productId]);
            } catch (Throwable $e2) {
            }
        }
    }

    unset($_SESSION['wishlist']);
}

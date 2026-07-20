<?php
declare(strict_types=1);

function cart_session_id(): string
{
    if (empty($_SESSION['cart_sid'])) {
        $_SESSION['cart_sid'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_sid'];
}

function get_or_create_cart(): array
{
    $pdo = db();
    $sid = cart_session_id();
    $stmt = $pdo->prepare('SELECT * FROM carts WHERE session_id = ? LIMIT 1');
    $stmt->execute([$sid]);
    $cart = $stmt->fetch();
    if ($cart) {
        return $cart;
    }
    $userId = current_user()['id'] ?? null;
    $ins = $pdo->prepare('INSERT INTO carts (session_id, user_id, currency) VALUES (?, ?, ?)');
    $ins->execute([$sid, $userId, current_currency()]);
    $stmt->execute([$sid]);
    return $stmt->fetch();
}

function cart_items(): array
{
    $cart = get_or_create_cart();
    $stmt = db()->prepare(
        'SELECT ci.*, p.name, p.slug, p.image, pv.label AS variant_label, pv.stock
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.cart_id = ?
         ORDER BY ci.id DESC'
    );
    $stmt->execute([$cart['id']]);
    return $stmt->fetchAll();
}

function cart_count(): int
{
    try {
        $items = cart_items();
        return (int) array_sum(array_column($items, 'quantity'));
    } catch (Throwable $e) {
        return 0;
    }
}

function cart_subtotal_gbp(): float
{
    $total = 0.0;
    foreach (cart_items() as $item) {
        $total += (float) $item['unit_price'] * (int) $item['quantity'];
    }
    return round($total, 2);
}

function cart_add(int $productId, int $variantId, int $qty = 1): array
{
    $pdo = db();
    $cart = get_or_create_cart();

    $v = $pdo->prepare('SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1');
    $v->execute([$variantId, $productId]);
    $variant = $v->fetch();
    if (!$variant) {
        return ['ok' => false, 'error' => 'Variant not found'];
    }
    if ((int) $variant['stock'] < $qty) {
        return ['ok' => false, 'error' => 'Not enough stock'];
    }

    $existing = $pdo->prepare('SELECT * FROM cart_items WHERE cart_id = ? AND variant_id = ?');
    $existing->execute([$cart['id'], $variantId]);
    $row = $existing->fetch();

    if ($row) {
        $newQty = (int) $row['quantity'] + $qty;
        if ($newQty > (int) $variant['stock']) {
            return ['ok' => false, 'error' => 'Not enough stock'];
        }
        $upd = $pdo->prepare('UPDATE cart_items SET quantity = ?, unit_price = ? WHERE id = ?');
        $upd->execute([$newQty, $variant['price'], $row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$cart['id'], $productId, $variantId, $qty, $variant['price']]);
    }

    return ['ok' => true, 'count' => cart_count(), 'subtotal' => money(cart_subtotal_gbp())];
}

/**
 * Add a gift card to the cart with a custom amount (GBP) and recipient details.
 * Gift items are never merged — each is a distinct card.
 */
function cart_add_gift(float $amount, string $recipientName, string $recipientEmail, string $senderName, string $message): array
{
    $pdo = db();
    $cart = get_or_create_cart();
    $productId = gift_product_id();
    $variantId = gift_variant_id();
    if ($productId <= 0 || $variantId <= 0) {
        return ['ok' => false, 'error' => 'Gift cards are unavailable right now'];
    }
    $bounds = gift_amount_bounds();
    $amount = round($amount, 2);
    if ($amount < $bounds['min'] || $amount > $bounds['max']) {
        return ['ok' => false, 'error' => 'Amount must be between ' . money($bounds['min']) . ' and ' . money($bounds['max'])];
    }
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid recipient email'];
    }

    $ins = $pdo->prepare(
        'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price, gift_recipient_name, gift_recipient_email, gift_sender_name, gift_message)
         VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $cart['id'], $productId, $variantId, $amount,
        trim($recipientName) ?: null,
        trim($recipientEmail),
        trim($senderName) ?: null,
        trim($message) ?: null,
    ]);

    return ['ok' => true, 'count' => cart_count(), 'subtotal' => money(cart_subtotal_gbp())];
}

/** Is this cart item a gift card? */
function cart_item_is_gift(array $item): bool
{
    return !empty($item['gift_recipient_email']) || (gift_product_id() > 0 && (int) ($item['product_id'] ?? 0) === gift_product_id());
}

function cart_update(int $itemId, int $qty): array
{
    $pdo = db();
    $cart = get_or_create_cart();
    if ($qty <= 0) {
        return cart_remove($itemId);
    }
    $stmt = $pdo->prepare(
        'SELECT ci.*, pv.stock FROM cart_items ci
         JOIN product_variants pv ON pv.id = ci.variant_id
         WHERE ci.id = ? AND ci.cart_id = ?'
    );
    $stmt->execute([$itemId, $cart['id']]);
    $item = $stmt->fetch();
    if (!$item) {
        return ['ok' => false, 'error' => 'Item not found'];
    }
    if ($qty > (int) $item['stock']) {
        return ['ok' => false, 'error' => 'Not enough stock'];
    }
    $upd = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
    $upd->execute([$qty, $itemId]);
    return ['ok' => true, 'count' => cart_count(), 'subtotal' => money(cart_subtotal_gbp())];
}

function cart_remove(int $itemId): array
{
    $cart = get_or_create_cart();
    $del = db()->prepare('DELETE FROM cart_items WHERE id = ? AND cart_id = ?');
    $del->execute([$itemId, $cart['id']]);
    return ['ok' => true, 'count' => cart_count(), 'subtotal' => money(cart_subtotal_gbp())];
}

function cart_clear(): void
{
    $cart = get_or_create_cart();
    db()->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cart['id']]);
}

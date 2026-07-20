<?php
declare(strict_types=1);

/** Preset gift card denominations (GBP). */
function gift_denominations(): array
{
    return [25, 50, 100, 150, 200, 250];
}

/** Min/max for custom gift card amounts (GBP). */
function gift_amount_bounds(): array
{
    return ['min' => 10.0, 'max' => 1000.0];
}

/** ID of the seeded "Gift Card" product, or 0 if missing. */
function gift_product_id(): int
{
    static $id = null;
    if ($id === null) {
        $id = (int) (db()->query('SELECT id FROM products WHERE slug = "gift-card" LIMIT 1')->fetchColumn() ?: 0);
    }
    return $id;
}

/** Active variant ID for the gift card product, or 0. */
function gift_variant_id(): int
{
    static $vid = null;
    if ($vid === null) {
        $pid = gift_product_id();
        $vid = $pid ? (int) (db()->query('SELECT id FROM product_variants WHERE product_id = ' . $pid . ' AND is_active = 1 ORDER BY id LIMIT 1')->fetchColumn() ?: 0) : 0;
    }
    return $vid;
}

/** Generate a unique, human-friendly gift card code. */
function gift_generate_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $blocks = [];
        for ($b = 0; $b < 3; $b++) {
            $s = '';
            for ($i = 0; $i < 4; $i++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $blocks[] = $s;
        }
        $code = 'GC-' . implode('-', $blocks);
        $exists = db()->prepare('SELECT 1 FROM gift_cards WHERE code = ?');
        $exists->execute([$code]);
    } while ($exists->fetchColumn());
    return $code;
}

/** Look up an active gift card with a positive balance by code. */
function gift_card_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM gift_cards WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $card = $stmt->fetch();
    return $card ?: null;
}

/**
 * Create a gift card record for a paid order line item and email the recipient.
 * Returns the generated code.
 */
function gift_card_issue(array $orderItem, array $order): string
{
    $amount = (float) ($orderItem['gift_amount'] ?? 0);
    $code = gift_generate_code();
    db()->prepare(
        'INSERT INTO gift_cards (code, initial_amount, balance, currency, recipient_name, recipient_email, sender_name, message, purchaser_email, order_id, status)
         VALUES (?, ?, ?, "GBP", ?, ?, ?, ?, ?, ?, "active")'
    )->execute([
        $code,
        $amount,
        $amount,
        $orderItem['gift_recipient_name'] ?? null,
        $orderItem['gift_recipient_email'] ?? null,
        $orderItem['gift_sender_name'] ?? null,
        $orderItem['gift_message'] ?? null,
        $order['email'] ?? null,
        (int) $order['id'],
    ]);

    gift_card_send_email($code, $amount, $orderItem);
    return $code;
}

/** Email the gift card to its recipient (falls back to purchaser handled by caller). */
function gift_card_send_email(string $code, float $amount, array $data): void
{
    if (!function_exists('mailer_enabled') || !mailer_enabled()) {
        return;
    }
    $to = trim((string) ($data['gift_recipient_email'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $store = setting('store_name', 'By Claudia Darlene');
    $recipient = e((string) ($data['gift_recipient_name'] ?? 'there'));
    $sender = e((string) ($data['gift_sender_name'] ?? 'Someone special'));
    $message = trim((string) ($data['gift_message'] ?? ''));
    $shopUrl = url('index.php?page=shop');

    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;">'
        . '<h2 style="font-family:Georgia,serif;">You\'ve received a gift card! 🎁</h2>'
        . '<p>Hi ' . $recipient . ',</p>'
        . '<p>' . $sender . ' sent you a <strong>&pound;' . number_format($amount, 2) . '</strong> gift card for ' . e($store) . '.</p>'
        . ($message !== '' ? '<blockquote style="border-left:3px solid #E8A8A8;margin:16px 0;padding:8px 16px;color:#555;">' . nl2br(e($message)) . '</blockquote>' : '')
        . '<div style="text-align:center;margin:24px 0;padding:20px;border:2px dashed #E8A8A8;border-radius:12px;">'
        . '<p style="margin:0 0 6px;font-size:12px;color:#888;text-transform:uppercase;letter-spacing:2px;">Your code</p>'
        . '<p style="margin:0;font-size:26px;font-weight:bold;letter-spacing:2px;font-family:monospace;">' . e($code) . '</p>'
        . '</div>'
        . '<p style="text-align:center;"><a href="' . e($shopUrl) . '" style="display:inline-block;background:#2c2420;color:#fff;text-decoration:none;padding:12px 28px;border-radius:999px;">Shop now</a></p>'
        . '<p style="color:#888;font-size:12px;">Enter the code at checkout to redeem your balance. ' . e($store) . '</p></div>';

    send_mail($to, 'You\'ve received a ' . $store . ' gift card', $html);
}

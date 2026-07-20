<?php
declare(strict_types=1);

/**
 * Payment gateways: Stripe (card, Afterpay/Clearpay, Klarna) and Paystack.
 * Credentials are configured via admin Integrations settings.
 */

function stripe_enabled(): bool
{
    return setting('payment_stripe_enabled', '0') === '1' && setting('stripe_secret_key', '') !== '';
}

function paystack_enabled(): bool
{
    return setting('payment_paystack_enabled', '0') === '1' && setting('paystack_secret_key', '') !== '';
}

/** Payment methods to offer at checkout: [key => label]. */
function available_payment_methods(): array
{
    $methods = [];
    if (stripe_enabled()) {
        $methods['stripe'] = 'Card (Stripe)';
        if (setting('payment_afterpay_enabled', '0') === '1') {
            $methods['afterpay_clearpay'] = 'Afterpay / Clearpay';
        }
        if (setting('payment_klarna_enabled', '0') === '1') {
            $methods['klarna'] = 'Klarna';
        }
    }
    if (paystack_enabled()) {
        $methods['paystack'] = 'Paystack (Card / Mobile Money)';
    }
    if (!$methods) {
        // Demo fallback so the store still works without live keys.
        $methods['demo'] = 'Demo checkout (no live payment)';
    }
    return $methods;
}

/** Minor units (e.g. pounds -> pence). All supported currencies are 2-decimal. */
function to_minor_units(float $amount): int
{
    return (int) round($amount * 100);
}

/** Which Stripe gateway a checkout method maps to. */
function stripe_method_types(string $method): array
{
    return match ($method) {
        'afterpay_clearpay' => ['afterpay_clearpay'],
        'klarna' => ['klarna'],
        default => ['card'],
    };
}

/**
 * Create a Stripe Checkout Session. Returns ['ok'=>bool,'url'=>?string,'error'=>?string].
 */
function stripe_create_checkout_session(array $order, string $successUrl, string $cancelUrl, string $method = 'stripe'): array
{
    $secret = (string) setting('stripe_secret_key', '');
    $fields = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'client_reference_id' => $order['order_number'],
        'customer_email' => $order['email'],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => strtolower($order['currency']),
                'unit_amount' => to_minor_units((float) $order['total']),
                'product_data' => ['name' => 'Order ' . $order['order_number']],
            ],
        ]],
        'metadata' => ['order_id' => (string) $order['id'], 'order_number' => $order['order_number']],
    ];
    foreach (stripe_method_types($method) as $i => $pmt) {
        $fields['payment_method_types'][$i] = $pmt;
    }

    $res = stripe_request('POST', 'https://api.stripe.com/v1/checkout/sessions', $secret, $fields);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }
    return ['ok' => true, 'url' => $res['data']['url'] ?? null, 'id' => $res['data']['id'] ?? null];
}

function stripe_retrieve_session(string $sessionId): array
{
    $secret = (string) setting('stripe_secret_key', '');
    return stripe_request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId), $secret);
}

function stripe_request(string $verb, string $url, string $secret, array $fields = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($verb === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        return ['ok' => false, 'error' => 'Stripe request failed: ' . $err];
    }
    $data = json_decode((string) $res, true);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'data' => $data];
    }
    return ['ok' => false, 'error' => $data['error']['message'] ?? ('Stripe HTTP ' . $code), 'data' => $data];
}

/**
 * Initialise a Paystack transaction. Returns ['ok','url','reference','error'].
 */
function paystack_initialize(array $order, string $callbackUrl): array
{
    $secret = (string) setting('paystack_secret_key', '');
    $payload = json_encode([
        'email' => $order['email'],
        'amount' => to_minor_units((float) $order['total']),
        'currency' => $order['currency'],
        'reference' => $order['order_number'],
        'callback_url' => $callbackUrl,
        'metadata' => ['order_id' => $order['id'], 'order_number' => $order['order_number']],
    ]);
    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        return ['ok' => false, 'error' => 'Paystack request failed: ' . $err];
    }
    $data = json_decode((string) $res, true);
    if (($data['status'] ?? false) === true) {
        return ['ok' => true, 'url' => $data['data']['authorization_url'] ?? null, 'reference' => $data['data']['reference'] ?? $order['order_number']];
    }
    return ['ok' => false, 'error' => $data['message'] ?? ('Paystack HTTP ' . $code)];
}

function paystack_verify(string $reference): array
{
    $secret = (string) setting('paystack_secret_key', '');
    $ch = curl_init('https://api.paystack.co/transaction/verify/' . urlencode($reference));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) {
        return ['ok' => false, 'error' => 'Paystack verify failed.'];
    }
    $data = json_decode((string) $res, true);
    $paid = ($data['status'] ?? false) === true && ($data['data']['status'] ?? '') === 'success';
    return [
        'ok' => (bool) ($data['status'] ?? false),
        'paid' => $paid,
        'amount' => isset($data['data']['amount']) ? ((float) $data['data']['amount'] / 100) : 0.0,
        'currency' => $data['data']['currency'] ?? '',
        'raw' => $data,
    ];
}

/**
 * Finalise a paid order once: record payment, decrement stock, mark paid, notify.
 * Idempotent — safe to call multiple times.
 */
function finalize_order_payment(int $orderId, string $provider, string $providerRef, float $amount, string $currency): bool
{
    $pdo = db();
    $o = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $o->execute([$orderId]);
    $order = $o->fetch();
    if (!$order) {
        return false;
    }
    if (in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered'], true)) {
        return true; // already finalised
    }

    $pdo->prepare('INSERT INTO payments (order_id, provider, provider_ref, amount, currency, status) VALUES (?,?,?,?,?,?)')
        ->execute([$orderId, $provider, $providerRef, $amount, $currency, 'succeeded']);

    // Decrement stock from the order's line items.
    $items = $pdo->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $it) {
        if (!empty($it['variant_id'])) {
            $pdo->prepare('UPDATE product_variants SET stock = MAX(0, stock - ?) WHERE id = ?')
                ->execute([(int) $it['quantity'], (int) $it['variant_id']]);
        }
    }

    $pdo->prepare('UPDATE orders SET status = ?, payment_method = ?, payment_ref = ? WHERE id = ?')
        ->execute(['paid', $provider, $providerRef, $orderId]);

    // Coupon usage (code stashed in session at checkout).
    if (!empty($_SESSION['pending_coupon'])) {
        $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?')->execute([$_SESSION['pending_coupon']]);
        unset($_SESSION['pending_coupon']);
    }

    // Draw down a redeemed gift card balance (GBP amount stashed at checkout).
    if (!empty($_SESSION['pending_giftcard']['code'])) {
        $gc = $_SESSION['pending_giftcard'];
        $pdo->prepare('UPDATE gift_cards SET balance = MAX(0, balance - ?), status = CASE WHEN balance - ? <= 0 THEN "redeemed" ELSE status END WHERE code = ?')
            ->execute([(float) $gc['amount'], (float) $gc['amount'], $gc['code']]);
        unset($_SESSION['pending_giftcard']);
    }

    // Issue any purchased gift cards (email codes to recipients).
    $gifts = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? AND gift_amount IS NOT NULL AND gift_amount > 0');
    $gifts->execute([$orderId]);
    foreach ($gifts->fetchAll() as $giftItem) {
        try {
            gift_card_issue($giftItem, $order);
        } catch (Throwable $e) {
            // Non-fatal: order still completes even if issuance/email hiccups.
        }
    }

    notify_order_paid($orderId);
    return true;
}

/** Send order confirmation email + SMS. */
function notify_order_paid(int $orderId): void
{
    $o = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $o->execute([$orderId]);
    $order = $o->fetch();
    if (!$order) {
        return;
    }
    $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

    $store = setting('store_name', 'By Claudia Darlene');
    $cur = $order['currency'];
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:6px 0;">' . e($it['product_name']) . ' (' . e((string) $it['variant_label']) . ') &times; ' . (int) $it['quantity']
            . '</td><td style="padding:6px 0;text-align:right;">' . $cur . ' ' . number_format((float) $it['line_total'], 2) . '</td></tr>';
    }
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;">'
        . '<h2 style="font-family:Georgia,serif;">Thank you for your order!</h2>'
        . '<p>Hi ' . e((string) ($order['shipping_name'] ?: 'there')) . ', we\'ve received your order <strong>' . e($order['order_number']) . '</strong> and it\'s now being prepared.</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows
        . '<tr><td style="padding-top:10px;">Subtotal</td><td style="padding-top:10px;text-align:right;">' . $cur . ' ' . number_format((float) $order['subtotal'], 2) . '</td></tr>'
        . '<tr><td>Shipping</td><td style="text-align:right;">' . $cur . ' ' . number_format((float) $order['shipping'], 2) . '</td></tr>'
        . ((float) $order['discount'] > 0 ? '<tr><td>Discount</td><td style="text-align:right;">-' . $cur . ' ' . number_format((float) $order['discount'], 2) . '</td></tr>' : '')
        . '<tr><td style="font-weight:bold;padding-top:8px;">Total</td><td style="font-weight:bold;text-align:right;padding-top:8px;">' . $cur . ' ' . number_format((float) $order['total'], 2) . '</td></tr>'
        . '</table>';

    $trackUrl = tracking_url($order['shipping_carrier'] ?? '', $order['tracking_number'] ?? '');
    if (!empty($order['shipping_carrier']) && $order['shipping_carrier'] !== 'standard') {
        $html .= '<p style="font-size:14px;margin-top:16px;">Shipping via <strong>' . e(carrier_label($order['shipping_carrier'])) . '</strong>.';
        if ($trackUrl) {
            $html .= ' Tracking number <strong>' . e((string) $order['tracking_number']) . '</strong> — <a href="' . e($trackUrl) . '">track your parcel</a>.';
        } else {
            $html .= ' You\'ll receive a tracking number once it ships.';
        }
        $html .= '</p>';
    }

    $html .= '<p style="color:#888;font-size:12px;margin-top:24px;">' . e($store) . '</p></div>';

    if (mailer_enabled() && !empty($order['email'])) {
        send_mail($order['email'], 'Order ' . $order['order_number'] . ' confirmed', $html);
    }
    if (sms_enabled() && !empty($order['phone'])) {
        $msg = $store . ': Order ' . $order['order_number'] . ' confirmed. Total ' . $cur . ' ' . number_format((float) $order['total'], 2) . '.';
        if ($trackUrl) {
            $msg .= ' Track ' . carrier_label($order['shipping_carrier']) . ': ' . $trackUrl;
        }
        $msg .= ' Thank you!';
        send_sms((string) $order['phone'], $msg);
    }
}

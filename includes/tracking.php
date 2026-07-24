<?php
declare(strict_types=1);

/**
 * Customer-facing fulfillment steps (happy path).
 *
 * @return list<array{key:string,label:string,desc:string}>
 */
function order_tracking_steps(): array
{
    return [
        ['key' => 'pending', 'label' => 'Order placed', 'desc' => 'We’ve received your order'],
        ['key' => 'paid', 'label' => 'Payment confirmed', 'desc' => 'Your payment went through'],
        ['key' => 'processing', 'label' => 'Preparing', 'desc' => 'Your order is being prepared in the studio'],
        ['key' => 'shipped', 'label' => 'Shipped', 'desc' => 'Your parcel is on the way'],
        ['key' => 'delivered', 'label' => 'Delivered', 'desc' => 'Your order has arrived'],
    ];
}

function order_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'paid' => 'Paid',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        default => ucfirst($status),
    };
}

/**
 * @return list<array{key:string,label:string,desc:string,state:string}>
 */
function order_tracking_progress(string $status): array
{
    $status = strtolower(trim($status));
    $steps = order_tracking_steps();
    $keys = array_column($steps, 'key');

    if (in_array($status, ['cancelled', 'refunded'], true)) {
        $out = [];
        foreach ($steps as $i => $step) {
            $out[] = [
                'key' => $step['key'],
                'label' => $step['label'],
                'desc' => $step['desc'],
                'state' => $i === 0 ? 'done' : 'muted',
            ];
        }
        $out[] = [
            'key' => $status,
            'label' => order_status_label($status),
            'desc' => $status === 'cancelled' ? 'This order was cancelled' : 'This order was refunded',
            'state' => 'current-bad',
        ];
        return $out;
    }

    $idx = array_search($status, $keys, true);
    if ($idx === false) {
        $idx = 0;
    }

    $out = [];
    foreach ($steps as $i => $step) {
        if ($i < $idx) {
            $state = 'done';
        } elseif ($i === $idx) {
            $state = 'current';
        } else {
            $state = 'upcoming';
        }
        $out[] = [
            'key' => $step['key'],
            'label' => $step['label'],
            'desc' => $step['desc'],
            'state' => $state,
        ];
    }
    return $out;
}

function phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', normalize_phone($phone)) ?? '';
}

/**
 * Find recent orders matching a phone number (digit-tolerant).
 *
 * @return list<array<string,mixed>>
 */
function orders_find_by_phone(string $phone, int $limit = 10): array
{
    $digits = phone_digits($phone);
    if (strlen($digits) < 8) {
        return [];
    }

    $needle = strlen($digits) > 10 ? substr($digits, -10) : $digits;
    $rows = db()->query(
        'SELECT * FROM orders
         WHERE phone IS NOT NULL AND phone != ""
         ORDER BY id DESC
         LIMIT 200'
    )->fetchAll();

    $matches = [];
    foreach ($rows as $row) {
        $rowDigits = phone_digits((string) ($row['phone'] ?? ''));
        if ($rowDigits === '') {
            continue;
        }
        if (
            $rowDigits === $digits
            || str_ends_with($rowDigits, $needle)
            || str_ends_with($digits, substr($rowDigits, -10))
        ) {
            $matches[] = $row;
            if (count($matches) >= $limit) {
                break;
            }
        }
    }
    return $matches;
}

/**
 * @return list<array<string,mixed>>
 */
function orders_for_user(array $user, int $limit = 30): array
{
    $stmt = db()->prepare(
        'SELECT * FROM orders
         WHERE user_id = ?
            OR (email IS NOT NULL AND email != "" AND email = ?)
            OR (phone IS NOT NULL AND phone != "" AND phone = ?)
         ORDER BY id DESC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([
        (int) $user['id'],
        (string) ($user['email'] ?? ''),
        (string) ($user['phone'] ?? ''),
    ]);
    return $stmt->fetchAll();
}

/** Render a visual status tracker for one order. */
function render_order_tracker(array $order, bool $compact = false): void
{
    $progress = order_tracking_progress((string) ($order['status'] ?? 'pending'));
    $trackUrl = tracking_url($order['shipping_carrier'] ?? '', $order['tracking_number'] ?? '');
    $sym = currency_symbol((string) ($order['currency'] ?? 'GBP'));
    ?>
    <article class="order-tracker<?= $compact ? ' order-tracker--compact' : '' ?>">
      <div class="order-tracker__head">
        <div>
          <p class="order-tracker__number"><?= e((string) $order['order_number']) ?></p>
          <p class="order-tracker__meta">
            <?= e(date('j M Y', strtotime((string) ($order['created_at'] ?? 'now')) ?: 'now')) ?>
            · <?= e($sym . number_format((float) ($order['total'] ?? 0), 2)) ?>
            · <?= e(order_status_label((string) ($order['status'] ?? 'pending'))) ?>
          </p>
        </div>
        <?php if ($trackUrl): ?>
          <a href="<?= e($trackUrl) ?>" target="_blank" rel="noopener" class="order-tracker__carrier">
            Track <?= e(carrier_label($order['shipping_carrier'] ?? '')) ?>
          </a>
        <?php elseif (!empty($order['shipping_carrier'])): ?>
          <span class="order-tracker__carrier order-tracker__carrier--muted">
            Via <?= e(carrier_label($order['shipping_carrier'])) ?>
          </span>
        <?php endif; ?>
      </div>

      <ol class="order-tracker__steps" aria-label="Order progress">
        <?php foreach ($progress as $step): ?>
          <li class="order-tracker__step is-<?= e($step['state']) ?>">
            <span class="order-tracker__dot" aria-hidden="true">
              <?php if ($step['state'] === 'done'): ?>
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/></svg>
              <?php elseif ($step['state'] === 'current' || $step['state'] === 'current-bad'): ?>
                <span class="order-tracker__pulse"></span>
              <?php endif; ?>
            </span>
            <div class="order-tracker__copy">
              <p class="order-tracker__label"><?= e($step['label']) ?></p>
              <p class="order-tracker__desc"><?= e($step['desc']) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>

      <?php if (!empty($order['tracking_number'])): ?>
        <p class="order-tracker__awb">
          Tracking number
          <span class="order-tracker__mono"><?= e((string) $order['tracking_number']) ?></span>
        </p>
      <?php endif; ?>
    </article>
    <?php
}

/**
 * Email the customer when an order status changes.
 */
function notify_order_status_changed(int $orderId, string $previousStatus, string $newStatus): bool
{
    if ($previousStatus === $newStatus) {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || empty($order['email'])) {
        return false;
    }

    $store = setting('store_name', 'By Claudia Darlene');
    $name = trim((string) ($order['shipping_name'] ?: 'there'));
    $first = explode(' ', $name)[0] ?: 'there';
    $newLabel = order_status_label($newStatus);
    $oldLabel = order_status_label($previousStatus);
    $trackPage = url('index.php?page=track');
    $trackUrl = tracking_url($order['shipping_carrier'] ?? '', $order['tracking_number'] ?? '');

    $extra = '';
    if ($newStatus === 'shipped') {
        $extra = '<p>Your parcel is on its way';
        if (!empty($order['shipping_carrier'])) {
            $extra .= ' via <strong>' . e(carrier_label($order['shipping_carrier'])) . '</strong>';
        }
        $extra .= '.</p>';
        if ($trackUrl) {
            $extra .= '<p><a href="' . e($trackUrl) . '">Track your shipment</a>';
            if (!empty($order['tracking_number'])) {
                $extra .= ' · ' . e((string) $order['tracking_number']);
            }
            $extra .= '</p>';
        } elseif (!empty($order['tracking_number'])) {
            $extra .= '<p>Tracking number: <strong>' . e((string) $order['tracking_number']) . '</strong></p>';
        }
    } elseif ($newStatus === 'delivered') {
        $extra = '<p>We hope you love your Hair by Claudia Darlene order.</p>';
    } elseif ($newStatus === 'processing') {
        $extra = '<p>Our studio is preparing your order now.</p>';
    } elseif ($newStatus === 'cancelled') {
        $extra = '<p>If you didn’t expect this, reply to this email and we’ll help.</p>';
    } elseif ($newStatus === 'refunded') {
        $extra = '<p>Your refund is being processed according to your original payment method.</p>';
    }

    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#1c1917;">'
        . '<h2 style="font-family:Georgia,serif;font-weight:500;">Order update</h2>'
        . '<p>Hi ' . e($first) . ', your order <strong>' . e((string) $order['order_number']) . '</strong> is now <strong>' . e($newLabel) . '</strong>'
        . ' (was ' . e($oldLabel) . ').</p>'
        . $extra
        . '<p style="margin-top:18px;"><a href="' . e($trackPage) . '" style="display:inline-block;background:#1c1917;color:#fff;padding:10px 16px;border-radius:999px;text-decoration:none;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;">Track your order</a></p>'
        . '<p style="color:#8a7a76;font-size:12px;margin-top:24px;">' . e($store) . '</p>'
        . '</div>';

    $sent = false;
    if (function_exists('mailer_enabled') && mailer_enabled()) {
        $sent = send_mail(
            (string) $order['email'],
            'Order ' . $order['order_number'] . ' is now ' . $newLabel,
            $html
        );
    }

    if (function_exists('sms_enabled') && sms_enabled() && !empty($order['phone'])) {
        $msg = $store . ': Order ' . $order['order_number'] . ' is now ' . $newLabel . '.';
        if ($trackUrl) {
            $msg .= ' Track: ' . $trackUrl;
        } else {
            $msg .= ' Track anytime: ' . $trackPage;
        }
        send_sms((string) $order['phone'], $msg);
    }

    return $sent;
}

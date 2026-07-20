<?php
declare(strict_types=1);

$pageTitle = 'Confirming payment…';
$robots = 'noindex, nofollow';
$gateway = (string) get('gateway', '');
$orderId = (int) get('order', 0);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

$ok = false;
$message = '';

if (!$order) {
    $message = 'Order not found.';
} elseif (in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered'], true)) {
    $ok = true; // already confirmed
} elseif ($gateway === 'stripe') {
    $sessionId = (string) get('session_id', '');
    if ($sessionId === '') {
        $message = 'Missing Stripe session.';
    } else {
        $res = stripe_retrieve_session($sessionId);
        if ($res['ok'] && (($res['data']['payment_status'] ?? '') === 'paid')) {
            $ref = $res['data']['payment_intent'] ?? $sessionId;
            $amount = isset($res['data']['amount_total']) ? ((float) $res['data']['amount_total'] / 100) : (float) $order['total'];
            finalize_order_payment($orderId, 'stripe', (string) $ref, $amount, (string) ($res['data']['currency'] ? strtoupper($res['data']['currency']) : $order['currency']));
            $ok = true;
        } else {
            $message = 'Payment was not completed. ' . ($res['error'] ?? '');
        }
    }
} elseif ($gateway === 'paystack') {
    $reference = (string) (get('reference', '') ?: $order['order_number']);
    $res = paystack_verify($reference);
    if ($res['paid']) {
        finalize_order_payment($orderId, 'paystack', $reference, $res['amount'] ?: (float) $order['total'], $res['currency'] ?: (string) $order['currency']);
        $ok = true;
    } else {
        $message = 'Payment was not completed or is still pending.';
    }
} else {
    $message = 'Unknown payment gateway.';
}

if ($ok) {
    cart_clear();
    $_SESSION['last_order'] = $order['order_number'];
    redirect('index.php?page=order-success&order=' . urlencode($order['order_number']));
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-20">
  <div class="max-w-xl mx-auto px-4 text-center">
    <h1 class="font-display text-4xl mb-4">Payment not completed</h1>
    <p class="text-brand-soft mb-8"><?= e($message ?: 'Something went wrong while confirming your payment.') ?></p>
    <?php if ($order): ?>
      <p class="text-sm text-brand-soft mb-6">Your order <strong><?= e($order['order_number']) ?></strong> is saved as pending. You can try paying again.</p>
    <?php endif; ?>
    <a href="<?= e(url('index.php?page=checkout')) ?>" class="btn-ink inline-block px-8 py-3.5 text-sm tracking-[0.14em] uppercase">Back to checkout</a>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

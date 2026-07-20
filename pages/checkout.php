<?php
declare(strict_types=1);

$pageTitle = 'Checkout – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$items = cart_items();
if (!$items) {
    flash('error', 'Your cart is empty.');
    redirect('index.php?page=cart');
}

$subtotal = cart_subtotal_gbp();
$shipMethods = shipping_methods();
$freeThreshold = (float) (setting('free_shipping_threshold', '') ?: 0);
$freeShip = $freeThreshold > 0 && $subtotal >= $freeThreshold;
$selectedMethod = (string) (post('shipping_method') ?: array_key_first($shipMethods));
if (!isset($shipMethods[$selectedMethod])) {
    $selectedMethod = array_key_first($shipMethods);
}
$shipping = $freeShip ? 0.0 : (float) $shipMethods[$selectedMethod]['rate'];
$shipCarrier = $shipMethods[$selectedMethod]['carrier'];
$user = current_user();
$error = null;

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session. Please try again.';
    } else {
        $email = trim((string) post('email'));
        $name = trim((string) post('shipping_name'));
        $address = trim((string) post('shipping_address'));
        $city = trim((string) post('shipping_city'));
        $country = trim((string) post('shipping_country'));
        $postcode = trim((string) post('shipping_postcode'));
        $phone = trim((string) post('phone', ''));
        $method = (string) post('payment_method', 'stripe');
        $couponCode = strtoupper(trim((string) post('coupon', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || $address === '' || $city === '') {
            $error = 'Please complete all required fields.';
        } else {
            $discount = 0.0;
            if ($couponCode !== '') {
                $cStmt = db()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
                $cStmt->execute([$couponCode]);
                $coupon = $cStmt->fetch();
                if (!$coupon || !$coupon['is_active']) {
                    $error = 'Invalid coupon code.';
                } elseif (!empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < strtotime('today')) {
                    $error = 'This coupon has expired.';
                } elseif (!empty($coupon['max_uses']) && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
                    $error = 'This coupon has reached its usage limit.';
                } elseif (!empty($coupon['min_order']) && $subtotal < (float) $coupon['min_order']) {
                    $error = 'This coupon requires a minimum order of ' . money((float) $coupon['min_order']) . '.';
                } elseif ($coupon['type'] === 'percent') {
                    $discount = round($subtotal * ((float) $coupon['value'] / 100), 2);
                } else {
                    $discount = min($subtotal, (float) $coupon['value']);
                }
            }

            // Gift card redemption (draws down a prepaid balance).
            $giftCode = strtoupper(trim((string) post('gift_card', '')));
            $giftApplied = 0.0;
            $giftCard = null;
            if (!$error && $giftCode !== '') {
                $giftCard = gift_card_find($giftCode);
                if (!$giftCard || $giftCard['status'] !== 'active' || (float) $giftCard['balance'] <= 0) {
                    $error = 'That gift card code is invalid or has no balance.';
                }
            }

            if ($error) {
                goto render;
            }

            // Validate chosen method against configured gateways.
            $methods = available_payment_methods();
            if (!isset($methods[$method])) {
                $method = array_key_first($methods);
            }

            $preGiftTotal = max(0, $subtotal + $shipping - $discount);
            if ($giftCard) {
                $giftApplied = min((float) $giftCard['balance'], $preGiftTotal);
            }
            $total = max(0, $preGiftTotal - $giftApplied);
            $currency = current_currency();
            $rate = (float) (currency_rates()[$currency]['rate_from_gbp'] ?? 1);
            $orderNo = order_number();
            $orderTotalCur = convert_price($total);

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO orders (order_number, user_id, email, phone, status, currency, exchange_rate, subtotal, shipping, discount, total, payment_method, shipping_name, shipping_address, shipping_city, shipping_country, shipping_postcode, shipping_carrier)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $orderNo,
                    $user['id'] ?? null,
                    $email,
                    $phone,
                    'pending',
                    $currency,
                    $rate,
                    convert_price($subtotal),
                    convert_price($shipping),
                    convert_price($discount),
                    $orderTotalCur,
                    $method,
                    $name,
                    $address,
                    $city,
                    $country,
                    $postcode,
                    $shipCarrier,
                ]);
                $orderId = (int) $pdo->lastInsertId();

                $itemIns = $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_label, quantity, unit_price, line_total, gift_recipient_name, gift_recipient_email, gift_sender_name, gift_message, gift_amount)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($items as $item) {
                    $line = (float) $item['unit_price'] * (int) $item['quantity'];
                    $isGift = cart_item_is_gift($item);
                    $itemIns->execute([
                        $orderId,
                        $item['product_id'],
                        $item['variant_id'],
                        $isGift ? 'Gift Card' : $item['name'],
                        $isGift ? money((float) $item['unit_price']) . ' gift card' : $item['variant_label'],
                        $item['quantity'],
                        convert_price((float) $item['unit_price']),
                        convert_price($line),
                        $item['gift_recipient_name'] ?? null,
                        $item['gift_recipient_email'] ?? null,
                        $item['gift_sender_name'] ?? null,
                        $item['gift_message'] ?? null,
                        $isGift ? (float) $item['unit_price'] : null,
                    ]);
                }

                if ($giftApplied > 0 && $giftCard) {
                    $pdo->prepare('UPDATE orders SET gift_card_code = ?, gift_card_amount = ? WHERE id = ?')
                        ->execute([$giftCard['code'], convert_price($giftApplied), $orderId]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Checkout failed. Please try again.';
                goto render;
            }

            // Stash coupon so usage is only counted once payment succeeds.
            $_SESSION['pending_coupon'] = $couponCode !== '' ? $couponCode : null;
            // Stash redeemed gift card (GBP) so balance is only drawn down once paid.
            $_SESSION['pending_giftcard'] = ($giftApplied > 0 && $giftCard)
                ? ['code' => $giftCard['code'], 'amount' => $giftApplied]
                : null;

            $orderCtx = [
                'id' => $orderId,
                'order_number' => $orderNo,
                'email' => $email,
                'currency' => $currency,
                'total' => $orderTotalCur,
                'phone' => $phone,
            ];

            // Route to the selected gateway.
            if ($method === 'demo') {
                finalize_order_payment($orderId, 'demo', 'DEMO-' . strtoupper(bin2hex(random_bytes(5))), $orderTotalCur, $currency);
                cart_clear();
                $_SESSION['last_order'] = $orderNo;
                redirect('index.php?page=order-success&order=' . urlencode($orderNo));
            } elseif ($method === 'paystack') {
                $callback = url('index.php?page=checkout-return&gateway=paystack&order=' . $orderId);
                $r = paystack_initialize($orderCtx, $callback);
                if ($r['ok'] && !empty($r['url'])) {
                    redirect($r['url']);
                }
                $error = 'Could not start Paystack payment: ' . ($r['error'] ?? 'unknown error');
            } else {
                // Stripe (card / afterpay_clearpay / klarna)
                $success = url('index.php?page=checkout-return&gateway=stripe&order=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}');
                $cancel = url('index.php?page=checkout');
                $r = stripe_create_checkout_session($orderCtx, $success, $cancel, $method);
                if ($r['ok'] && !empty($r['url'])) {
                    redirect($r['url']);
                }
                $error = 'Could not start Stripe payment: ' . ($r['error'] ?? 'unknown error');
            }
        }
    }
}

render:
require ROOT_PATH . '/includes/header.php';
?>

<section class="py-14 sm:py-20">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <h1 class="font-display text-5xl mb-10 text-center">Checkout</h1>

    <?php if ($error): ?>
      <div class="mb-6 rounded-2xl bg-rose-50 text-rose-800 px-4 py-3 text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-[1.3fr_0.9fr] gap-8">
      <form method="post" class="bg-white/70 border border-brand-ink/5 rounded-3xl p-6 sm:p-8 space-y-5">
        <?= csrf_field() ?>
        <h2 class="font-display text-2xl">Shipping details</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Email *</label>
            <input name="email" type="email" required value="<?= e($user['email'] ?? '') ?>" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div class="sm:col-span-2">
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Full name *</label>
            <input name="shipping_name" required value="<?= e($user['name'] ?? '') ?>" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div class="sm:col-span-2" data-address-autocomplete>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Address *</label>
            <div class="relative mt-1">
              <input
                id="shipping_address"
                name="shipping_address"
                required
                autocomplete="off"
                autocapitalize="words"
                placeholder="Start typing your street address"
                class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm"
                aria-autocomplete="list"
                aria-controls="address-suggest-list"
                aria-expanded="false"
              >
              <ul id="address-suggest-list" class="address-suggest" role="listbox" hidden></ul>
            </div>
            <p class="mt-1.5 text-[11px] text-brand-soft">Suggestions from OpenStreetMap · city &amp; country fill when empty</p>
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">City *</label>
            <input id="shipping_city" name="shipping_city" required autocomplete="address-level2" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Postcode</label>
            <input id="shipping_postcode" name="shipping_postcode" autocomplete="postal-code" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Country</label>
            <input id="shipping_country" name="shipping_country" value="United Kingdom" data-autofill="1" autocomplete="country-name" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Phone</label>
            <input name="phone" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Coupon code</label>
            <input name="coupon" placeholder="SUMMER10" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Gift card</label>
            <input name="gift_card" placeholder="GC-XXXX-XXXX-XXXX" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm uppercase placeholder:normal-case">
          </div>
        </div>

        <h2 class="font-display text-2xl pt-4">Shipping method</h2>
        <?php if ($freeShip): ?>
          <p class="text-sm text-emerald-600 font-medium">You've qualified for free shipping.</p>
        <?php endif; ?>
        <div class="grid gap-3">
          <?php foreach ($shipMethods as $mid => $m): ?>
            <?php $cost = $freeShip ? 0.0 : (float) $m['rate']; ?>
            <label class="cursor-pointer">
              <input type="radio" name="shipping_method" value="<?= e($mid) ?>" class="peer sr-only ship-radio"
                     data-cost="<?= e((string) convert_price($cost)) ?>" <?= $mid === $selectedMethod ? 'checked' : '' ?>>
              <span class="flex items-center justify-between rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm peer-checked:border-brand-ink peer-checked:bg-brand-ink/5 transition">
                <span class="flex items-center gap-2">
                  <?php if ($m['carrier'] === 'dhl'): ?><span class="inline-block px-2 py-0.5 rounded bg-[#ffcc00] text-[#d40511] text-[11px] font-bold">DHL</span>
                  <?php elseif ($m['carrier'] === 'fedex'): ?><span class="inline-block px-2 py-0.5 rounded bg-[#4d148c] text-white text-[11px] font-bold">FedEx</span><?php endif; ?>
                  <?= e($m['label']) ?>
                </span>
                <span class="font-medium"><?= $cost <= 0 ? '<span class="text-emerald-600">Free</span>' : money($cost) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>

        <h2 class="font-display text-2xl pt-4">Payment method</h2>
        <?php $payMethods = available_payment_methods(); ?>
        <?php if (isset($payMethods['demo'])): ?>
          <p class="text-sm text-brand-soft">Demo mode is active — orders complete instantly. Add live keys under Admin → Integrations.</p>
        <?php else: ?>
          <p class="text-sm text-brand-soft">You'll be securely redirected to complete payment.</p>
        <?php endif; ?>
        <div class="grid sm:grid-cols-2 gap-3">
          <?php $first = true; foreach ($payMethods as $val => $label): ?>
            <label class="cursor-pointer">
              <input type="radio" name="payment_method" value="<?= e($val) ?>" class="peer sr-only" <?= $first ? 'checked' : '' ?>>
              <span class="block rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm peer-checked:bg-brand-ink peer-checked:text-white transition"><?= e($label) ?></span>
            </label>
          <?php $first = false; endforeach; ?>
        </div>

        <button class="btn-ink w-full py-3.5 text-sm tracking-[0.14em] uppercase mt-4">Place Order · <span data-order-total><?= money($subtotal + $shipping) ?></span></button>
      </form>

      <aside class="bg-brand-mist rounded-3xl p-6 sm:p-8 h-fit">
        <h2 class="font-display text-2xl mb-5">Order summary</h2>
        <ul class="space-y-4 mb-6">
          <?php foreach ($items as $item): ?>
            <li class="flex justify-between gap-3 text-sm">
              <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?><br><span class="text-brand-soft"><?= e($item['variant_label']) ?></span></span>
              <span><?= money((float) $item['unit_price'] * (int) $item['quantity']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="space-y-2 text-sm border-t border-brand-ink/10 pt-4">
          <div class="flex justify-between"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
          <div class="flex justify-between"><span>Shipping</span><span data-ship-line><?= $shipping <= 0 ? '<span class="text-emerald-600 font-medium">Free</span>' : money($shipping) ?></span></div>
          <?php if ($freeThreshold > 0 && !$freeShip): ?>
            <p class="text-xs text-brand-soft">Add <?= money($freeThreshold - $subtotal) ?> more for free shipping.</p>
          <?php endif; ?>
          <div class="flex justify-between font-medium text-base pt-2"><span>Total</span><span data-summary-total><?= money($subtotal + $shipping) ?></span></div>
        </div>
      </aside>
    </div>
  </div>
</section>

<script>
(() => {
  const radios = document.querySelectorAll('.ship-radio');
  if (radios.length) {
    const subtotal = <?= json_encode(convert_price($subtotal)) ?>;
    const symbol = <?= json_encode(currency_symbol()) ?>;
    const fmt = (n) => symbol + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const shipLine = document.querySelector('[data-ship-line]');
    const totals = document.querySelectorAll('[data-summary-total], [data-order-total]');
    const update = (r) => {
      const cost = parseFloat(r.dataset.cost) || 0;
      if (shipLine) shipLine.innerHTML = cost <= 0 ? '<span class="text-emerald-600 font-medium">Free</span>' : fmt(cost);
      totals.forEach((t) => t.textContent = fmt(subtotal + cost));
    };
    radios.forEach((r) => r.addEventListener('change', () => update(r)));
    const checked = document.querySelector('.ship-radio:checked');
    if (checked) update(checked);
  }

  const wrap = document.querySelector('[data-address-autocomplete]');
  const address = document.getElementById('shipping_address');
  const city = document.getElementById('shipping_city');
  const postcode = document.getElementById('shipping_postcode');
  const country = document.getElementById('shipping_country');
  const list = document.getElementById('address-suggest-list');
  if (!wrap || !address || !list) return;

  const base = (window.APP && window.APP.baseUrl) ? window.APP.baseUrl : '';
  let timer = null;
  let active = -1;
  let items = [];
  let abort = null;

  const canAutofill = (el) => {
    if (!el) return false;
    const v = (el.value || '').trim();
    if (v === '') return true;
    return el.dataset.autofill === '1';
  };

  const markManual = (el) => {
    if (!el) return;
    delete el.dataset.autofill;
  };

  [city, postcode, country].forEach((el) => {
    if (!el) return;
    el.addEventListener('input', () => markManual(el));
  });

  const hide = () => {
    list.hidden = true;
    list.innerHTML = '';
    active = -1;
    items = [];
    address.setAttribute('aria-expanded', 'false');
  };

  const apply = (item) => {
    if (!item) return;
    address.value = item.address || item.label || '';
    if (canAutofill(city) && item.city) {
      city.value = item.city;
      city.dataset.autofill = '1';
    }
    if (canAutofill(postcode) && item.postcode) {
      postcode.value = item.postcode;
      postcode.dataset.autofill = '1';
    }
    if (canAutofill(country) && item.country) {
      country.value = item.country;
      country.dataset.autofill = '1';
    }
    hide();
    address.focus();
  };

  const render = () => {
    if (!items.length) {
      hide();
      return;
    }
    list.innerHTML = items.map((item, i) => `
      <li role="option" data-i="${i}" class="address-suggest__item${i === active ? ' is-active' : ''}" aria-selected="${i === active ? 'true' : 'false'}">
        ${escapeHtml(item.label)}
      </li>
    `).join('');
    list.hidden = false;
    address.setAttribute('aria-expanded', 'true');
  };

  const escapeHtml = (s) => String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  const search = async (q) => {
    if (abort) abort.abort();
    abort = new AbortController();
    try {
      const res = await fetch(`${base}/api/address-suggest.php?q=${encodeURIComponent(q)}`, {
        credentials: 'same-origin',
        signal: abort.signal,
      });
      const data = await res.json();
      items = Array.isArray(data.suggestions) ? data.suggestions : [];
      active = items.length ? 0 : -1;
      render();
    } catch (err) {
      if (err.name !== 'AbortError') hide();
    }
  };

  address.addEventListener('input', () => {
    const q = address.value.trim();
    clearTimeout(timer);
    if (q.length < 3) {
      hide();
      return;
    }
    timer = setTimeout(() => search(q), 320);
  });

  address.addEventListener('keydown', (e) => {
    if (list.hidden || !items.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      active = (active + 1) % items.length;
      render();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active - 1 + items.length) % items.length;
      render();
    } else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      apply(items[active]);
    } else if (e.key === 'Escape') {
      hide();
    }
  });

  list.addEventListener('mousedown', (e) => {
    const li = e.target.closest('[data-i]');
    if (!li) return;
    e.preventDefault();
    apply(items[Number(li.dataset.i)]);
  });

  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) hide();
  });
})();
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

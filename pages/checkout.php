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

$form = [
    'email' => '',
    'shipping_name' => '',
    'shipping_address' => '',
    'shipping_city' => '',
    'shipping_postcode' => '',
    'shipping_country' => 'United Kingdom',
    'phone' => '',
    'coupon' => '',
    'gift_card' => '',
];

if ($user) {
    $form['email'] = (string) ($user['email'] ?? '');
    $form['shipping_name'] = (string) ($user['name'] ?? '');
    $form['phone'] = (string) ($user['phone'] ?? '');

    $lastOrderStmt = db()->prepare(
        'SELECT email, phone, shipping_name, shipping_address, shipping_city, shipping_country, shipping_postcode
         FROM orders
         WHERE user_id = ?
            OR (email IS NOT NULL AND email != "" AND email = ?)
         ORDER BY id DESC
         LIMIT 1'
    );
    $lastOrderStmt->execute([(int) $user['id'], (string) ($user['email'] ?? '')]);
    $lastOrder = $lastOrderStmt->fetch() ?: null;

    if ($lastOrder) {
        foreach (
            [
                'email',
                'phone',
                'shipping_name',
                'shipping_address',
                'shipping_city',
                'shipping_country',
                'shipping_postcode',
            ] as $key
        ) {
            $value = trim((string) ($lastOrder[$key] ?? ''));
            if ($value !== '') {
                $form[$key] = $value;
            }
        }
    }
}

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

        $form = [
            'email' => $email,
            'shipping_name' => $name,
            'shipping_address' => $address,
            'shipping_city' => $city,
            'shipping_postcode' => $postcode,
            'shipping_country' => $country !== '' ? $country : 'United Kingdom',
            'phone' => $phone,
            'coupon' => (string) post('coupon', ''),
            'gift_card' => (string) post('gift_card', ''),
        ];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || $address === '' || $city === '') {
            $error = 'Please complete all required fields.';
        } else {
            $discount = 0.0;
            if ($couponCode !== '') {
                $couponCheck = coupon_validate($couponCode, $subtotal);
                if (!$couponCheck['valid']) {
                    $error = $couponCheck['message'] !== '' ? $couponCheck['message'] . '.' : 'Invalid coupon code.';
                } else {
                    $discount = (float) ($couponCheck['discount'] ?? 0);
                }
            }

            // Gift card redemption (draws down a prepaid balance).
            $giftCode = strtoupper(trim((string) post('gift_card', '')));
            $giftApplied = 0.0;
            $giftCard = null;
            if (!$error && $giftCode !== '') {
                $giftCheck = gift_card_validate($giftCode);
                if (!$giftCheck['valid']) {
                    $error = $giftCheck['message'] !== '' ? $giftCheck['message'] . '.' : 'That gift card code is invalid or has no balance.';
                } else {
                    $giftCard = gift_card_find($giftCode);
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
        <?php if ($user): ?>
          <p class="text-sm text-brand-soft -mt-2">Signed in as <?= e((string) $user['name']) ?> — we filled in your saved details. Edit anything before paying.</p>
        <?php endif; ?>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Email *</label>
            <input name="email" type="email" required autocomplete="email" value="<?= e($form['email']) ?>" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div class="sm:col-span-2">
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Full name *</label>
            <input name="shipping_name" required autocomplete="name" value="<?= e($form['shipping_name']) ?>" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div class="sm:col-span-2" data-address-autocomplete>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Address *</label>
            <div class="relative mt-1">
              <input
                id="shipping_address"
                name="shipping_address"
                required
                autocomplete="street-address"
                autocapitalize="words"
                placeholder="Start typing your street address"
                value="<?= e($form['shipping_address']) ?>"
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
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft" for="shipping_city">City *</label>
            <div class="place-combobox mt-1" data-place-combobox="city">
              <input
                id="shipping_city"
                name="shipping_city"
                required
                autocomplete="address-level2"
                placeholder="Search city"
                value="<?= e($form['shipping_city']) ?>"
                class="auth-input place-combobox__input"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="city-suggest-list"
                aria-expanded="false"
                <?= $form['shipping_city'] === '' ? ' data-autofill="1"' : '' ?>
              >
              <button type="button" class="place-combobox__chevron" data-place-toggle aria-label="Browse cities">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <ul id="city-suggest-list" class="address-suggest" role="listbox" hidden></ul>
            </div>
            <p class="mt-1.5 text-[11px] text-brand-soft">Type to search, or open the list</p>
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft" for="shipping_postcode">Postcode</label>
            <input id="shipping_postcode" name="shipping_postcode" autocomplete="postal-code" value="<?= e($form['shipping_postcode']) ?>" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft" for="shipping_country">Country</label>
            <div class="place-combobox mt-1" data-place-combobox="country">
              <input
                id="shipping_country"
                name="shipping_country"
                autocomplete="country-name"
                placeholder="Search country"
                value="<?= e($form['shipping_country']) ?>"
                class="auth-input place-combobox__input"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="country-suggest-list"
                aria-expanded="false"
                <?= ($form['shipping_country'] === 'United Kingdom' || $form['shipping_country'] === '') ? ' data-autofill="1"' : '' ?>
              >
              <button type="button" class="place-combobox__chevron" data-place-toggle aria-label="Browse countries">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7"/></svg>
              </button>
              <ul id="country-suggest-list" class="address-suggest" role="listbox" hidden></ul>
            </div>
            <p class="mt-1.5 text-[11px] text-brand-soft">Search or pick from the full country list</p>
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft">Phone</label>
            <input name="phone" type="tel" autocomplete="tel" value="<?= e($form['phone']) ?>" class="mt-1 w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft" for="checkout-coupon">Coupon code</label>
            <div class="code-field mt-1" data-code-validate="coupon">
              <input
                id="checkout-coupon"
                name="coupon"
                placeholder="SUMMER10"
                value="<?= e($form['coupon']) ?>"
                autocomplete="off"
                spellcheck="false"
                class="code-field__input"
                data-code-input
              >
              <span class="code-field__status" data-code-status aria-hidden="true">
                <svg class="code-field__icon code-field__icon--spin" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" opacity="0.25"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <svg class="code-field__icon code-field__icon--ok" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg class="code-field__icon code-field__icon--bad" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
              </span>
            </div>
            <p class="code-field__msg" data-code-msg hidden></p>
          </div>
          <div>
            <label class="text-xs tracking-[0.14em] uppercase text-brand-soft" for="checkout-gift">Gift card</label>
            <div class="code-field mt-1" data-code-validate="gift">
              <input
                id="checkout-gift"
                name="gift_card"
                placeholder="GC-XXXX-XXXX-XXXX"
                value="<?= e($form['gift_card']) ?>"
                autocomplete="off"
                spellcheck="false"
                class="code-field__input uppercase placeholder:normal-case"
                data-code-input
              >
              <span class="code-field__status" data-code-status aria-hidden="true">
                <svg class="code-field__icon code-field__icon--spin" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" opacity="0.25"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <svg class="code-field__icon code-field__icon--ok" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg class="code-field__icon code-field__icon--bad" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
              </span>
            </div>
            <p class="code-field__msg" data-code-msg hidden></p>
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

  // Live coupon / gift card validation
  const bindCodeValidate = (root) => {
    const input = root.querySelector('[data-code-input]');
    const msg = root.parentElement?.querySelector('[data-code-msg]');
    if (!input) return;
    const type = root.getAttribute('data-code-validate');
    let timer = null;
    let abort = null;
    let reqId = 0;

    const setState = (state, message = '') => {
      root.classList.remove('is-loading', 'is-valid', 'is-invalid');
      if (state) root.classList.add(`is-${state}`);
      if (msg) {
        const show = !!message;
        msg.hidden = !show;
        msg.textContent = message;
        msg.classList.toggle('is-ok', state === 'valid');
        msg.classList.toggle('is-bad', state === 'invalid');
      }
    };

    const validate = async () => {
      const code = (input.value || '').trim();
      if (!code) {
        setState('', '');
        return;
      }
      const id = ++reqId;
      setState('loading', 'Checking…');
      if (abort) abort.abort();
      abort = new AbortController();
      try {
        const url = `${base}/api/validate-code.php?type=${encodeURIComponent(type)}&code=${encodeURIComponent(code)}`;
        const res = await fetch(url, { credentials: 'same-origin', signal: abort.signal });
        const data = await res.json();
        if (id !== reqId) return;
        if (data.valid) {
          setState('valid', data.message || 'Valid');
        } else {
          setState('invalid', data.message || 'Invalid code');
        }
      } catch (err) {
        if (err.name === 'AbortError' || id !== reqId) return;
        setState('invalid', 'Could not verify code');
      }
    };

    input.addEventListener('input', () => {
      clearTimeout(timer);
      const code = (input.value || '').trim();
      if (!code) {
        setState('', '');
        return;
      }
      setState('loading', 'Checking…');
      timer = setTimeout(validate, 450);
    });

    input.addEventListener('blur', () => {
      clearTimeout(timer);
      validate();
    });

    if ((input.value || '').trim()) {
      validate();
    }
  };

  const base = (window.APP && window.APP.baseUrl) ? window.APP.baseUrl : '';
  document.querySelectorAll('[data-code-validate]').forEach(bindCodeValidate);

  const countries = <?= json_encode(geo_countries(), JSON_UNESCAPED_UNICODE) ?>;

  const escapeHtml = (s) => String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

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

  const createCombobox = ({ root, input, list, getItems, onSelect, remote = false }) => {
    if (!root || !input || !list) return null;
    let timer = null;
    let active = -1;
    let items = [];
    let abort = null;

    const hide = () => {
      list.hidden = true;
      list.innerHTML = '';
      active = -1;
      items = [];
      input.setAttribute('aria-expanded', 'false');
      root.classList.remove('is-open');
    };

    const render = () => {
      if (!items.length) {
        list.innerHTML = '<li class="address-suggest__item address-suggest__empty">No matches</li>';
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        return;
      }
      list.innerHTML = items.map((item, i) => `
        <li role="option" data-i="${i}" class="address-suggest__item${i === active ? ' is-active' : ''}" aria-selected="${i === active ? 'true' : 'false'}">
          ${escapeHtml(item.label)}
        </li>
      `).join('');
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      root.classList.add('is-open');
    };

    const load = async (q) => {
      const query = (q || '').trim();
      if (remote) {
        if (abort) abort.abort();
        abort = new AbortController();
        try {
          items = await getItems(query, abort.signal);
          active = items.length ? 0 : -1;
          render();
        } catch (err) {
          if (err.name !== 'AbortError') hide();
        }
        return;
      }
      items = getItems(query) || [];
      active = items.length ? 0 : -1;
      render();
    };

    const apply = (item) => {
      if (!item) return;
      onSelect(item);
      hide();
    };

    input.addEventListener('focus', () => load(input.value));
    input.addEventListener('input', () => {
      markManual(input);
      clearTimeout(timer);
      timer = setTimeout(() => load(input.value), remote ? 280 : 0);
    });

    input.addEventListener('keydown', (e) => {
      if (list.hidden && (e.key === 'ArrowDown' || e.key === 'Enter')) {
        load(input.value);
        return;
      }
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

    const toggle = root.querySelector('[data-place-toggle]');
    if (toggle) {
      toggle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        if (list.hidden) {
          input.focus();
          load(input.value);
        } else {
          hide();
        }
      });
    }

    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) hide();
    });

    return { hide, load, input };
  };

  const countryRoot = document.querySelector('[data-place-combobox="country"]');
  const countryInput = document.getElementById('shipping_country');
  const countryList = document.getElementById('country-suggest-list');
  createCombobox({
    root: countryRoot,
    input: countryInput,
    list: countryList,
    getItems: (q) => {
      const query = q.trim().toLowerCase();
      const priority = ['GB', 'GH', 'US', 'NG', 'CA', 'IE', 'FR', 'DE', 'NL', 'AU', 'AE', 'ZA'];
      let list = countries.filter((c) => !query || c.name.toLowerCase().includes(query) || c.code.toLowerCase().includes(query));
      if (!query) {
        const preferred = priority
          .map((code) => countries.find((c) => c.code === code))
          .filter(Boolean);
        const rest = list.filter((c) => !priority.includes(c.code));
        list = preferred.concat(rest);
      }
      return list.slice(0, 12).map((c) => ({ label: c.name, name: c.name, code: c.code }));
    },
    onSelect: (item) => {
      countryInput.value = item.name;
      markManual(countryInput);
    },
  });

  const cityRoot = document.querySelector('[data-place-combobox="city"]');
  const cityInput = document.getElementById('shipping_city');
  const cityList = document.getElementById('city-suggest-list');
  createCombobox({
    root: cityRoot,
    input: cityInput,
    list: cityList,
    remote: true,
    getItems: async (q, signal) => {
      const country = (countryInput && countryInput.value || '').trim();
      const url = `${base}/api/city-suggest.php?q=${encodeURIComponent(q)}&country=${encodeURIComponent(country)}`;
      const res = await fetch(url, { credentials: 'same-origin', signal });
      const data = await res.json();
      return Array.isArray(data.suggestions) ? data.suggestions : [];
    },
    onSelect: (item) => {
      cityInput.value = item.city || item.label || '';
      markManual(cityInput);
      if (item.country && countryInput && canAutofill(countryInput)) {
        countryInput.value = item.country;
        countryInput.dataset.autofill = '1';
      }
    },
  });

  const wrap = document.querySelector('[data-address-autocomplete]');
  const address = document.getElementById('shipping_address');
  const city = document.getElementById('shipping_city');
  const postcode = document.getElementById('shipping_postcode');
  const country = document.getElementById('shipping_country');
  const list = document.getElementById('address-suggest-list');
  if (!wrap || !address || !list) return;

  let timer = null;
  let active = -1;
  let items = [];
  let abort = null;

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

<?php
declare(strict_types=1);

$pageTitle = 'Track Order – Hair by Claudia Darlene';
$pageDescription = 'Track your Hair by Claudia Darlene order with your phone number.';
$robots = 'noindex, follow';

$phone = '';
$error = null;
$orders = [];
$searched = false;

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session. Please try again.';
    } else {
        $phone = trim((string) post('phone', ''));
        $searched = true;
        if (!is_valid_phone($phone)) {
            $error = 'Enter a valid phone number (the one used at checkout).';
        } else {
            $orders = orders_find_by_phone($phone);
            if (!$orders) {
                $error = 'No orders found for that phone number. Check the number and try again.';
            }
        }
    }
} elseif ($user = current_user()) {
    // Prefill phone for signed-in customers
    $phone = (string) ($user['phone'] ?? '');
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="track-page">
  <div class="track-page__inner">
    <header class="track-hero">
      <p class="track-hero__eyebrow">Order tracking</p>
      <h1 class="track-hero__title font-display">Track your order</h1>
      <p class="track-hero__lead">Enter the phone number used at checkout to see live progress — from payment to delivery.</p>
    </header>

    <form method="post" class="track-form" autocomplete="on">
      <?= csrf_field() ?>
      <label class="track-form__label" for="track-phone">Phone number</label>
      <div class="track-form__row">
        <input
          id="track-phone"
          type="tel"
          name="phone"
          required
          inputmode="tel"
          autocomplete="tel"
          placeholder="+44 7342 590296"
          value="<?= e($phone) ?>"
          class="track-form__input"
        >
        <button type="submit" class="btn-ink track-form__btn">Track</button>
      </div>
      <p class="track-form__hint">We’ll match recent orders linked to this number.</p>
    </form>

    <?php if ($error): ?>
      <div class="track-alert" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($searched && !$error && $orders): ?>
      <div class="track-results">
        <p class="track-results__count"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> found</p>
        <?php foreach ($orders as $order): ?>
          <?php render_order_tracker($order); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="track-page__note">
      Signed in?
      <a href="<?= e(url('index.php?page=account&tab=tracking')) ?>">Open your Tracking tab</a>
      for every order on your account.
    </p>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

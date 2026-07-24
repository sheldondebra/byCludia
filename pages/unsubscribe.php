<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

$email = strtolower(trim((string) get('email', '')));
$done = false;
$error = '';

if (request_method() === 'POST' && verify_csrf(post('csrf_token'))) {
    $email = strtolower(trim((string) post('email', '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        email_unsubscribe($email, 'user_request');
        $done = true;
    }
}

$pageTitle = 'Unsubscribe – Hair by Claudia Darlene';
$pageDescription = 'Unsubscribe from marketing emails.';
require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-lg mx-auto px-6 text-center">
    <p class="text-xs tracking-[0.28em] uppercase text-brand-soft mb-3">Email preferences</p>
    <h1 class="font-display text-4xl sm:text-5xl mb-4">Unsubscribe</h1>
    <?php if ($done): ?>
      <p class="text-brand-soft leading-relaxed mb-8">You’ve been removed from marketing emails for <strong><?= e($email) ?></strong>.</p>
      <a href="<?= e(url('index.php?page=home')) ?>" class="btn-ink inline-flex px-8 py-3 text-sm tracking-[0.12em] uppercase">Back to shop</a>
    <?php else: ?>
      <p class="text-brand-soft leading-relaxed mb-8">Enter your email to stop receiving marketing messages from <?= e(setting('store_name', 'By Claudia Darlene') ?: 'us') ?>.</p>
      <?php if ($error): ?><p class="mb-4 text-sm text-rose-700"><?= e($error) ?></p><?php endif; ?>
      <form method="post" class="text-left space-y-4 border border-brand-ink/10 rounded-2xl p-6 bg-white/70">
        <?= csrf_field() ?>
        <div>
          <label class="text-xs tracking-widest uppercase text-brand-soft block mb-2" for="unsub-email">Email</label>
          <input id="unsub-email" type="email" name="email" required value="<?= e($email) ?>" class="w-full rounded-xl border border-brand-ink/15 px-4 py-3 text-sm bg-white">
        </div>
        <button class="btn-ink w-full py-3 text-sm tracking-[0.12em] uppercase">Unsubscribe</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

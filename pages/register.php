<?php
declare(strict_types=1);

$pageTitle = 'Create Account – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$error = null;

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session.';
    } else {
        $result = register_user((string) post('name'), (string) post('email'), (string) post('password'));
        if ($result['ok']) {
            flash('success', 'Account created. Welcome!');
            redirect('index.php?page=account');
        }
        $error = $result['error'] ?? 'Registration failed.';
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-md mx-auto px-6">
    <h1 class="font-display text-5xl text-center mb-8">Create Account</h1>
    <?php if ($error): ?><div class="mb-4 rounded-2xl bg-rose-50 text-rose-800 px-4 py-3 text-sm"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="bg-white/70 border border-brand-ink/5 rounded-3xl p-6 sm:p-8 space-y-4">
      <?= csrf_field() ?>
      <input type="text" name="name" required placeholder="Full name" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
      <input type="email" name="email" required placeholder="Email" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
      <input type="password" name="password" required minlength="8" placeholder="Password (min 8)" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
      <button class="btn-ink w-full py-3 text-sm tracking-[0.12em] uppercase">Register</button>
    </form>
    <p class="text-center text-sm text-brand-soft mt-6">Already have an account? <a class="underline text-brand-ink" href="<?= e(url('index.php?page=login')) ?>">Sign in</a></p>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

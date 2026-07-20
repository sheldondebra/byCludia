<?php
declare(strict_types=1);

$pageTitle = 'Sign In – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$error = null;

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session.';
    } elseif (attempt_login((string) post('email'), (string) post('password'))) {
        $user = current_user();
        if ($user && $user['role'] === 'admin') {
            redirect('admin/index.php');
        }
        flash('success', 'Welcome back!');
        redirect('index.php?page=account');
    } else {
        $error = 'Invalid email or password.';
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-md mx-auto px-6">
    <h1 class="font-display text-5xl text-center mb-8">Sign In</h1>
    <?php if ($error): ?><div class="mb-4 rounded-2xl bg-rose-50 text-rose-800 px-4 py-3 text-sm"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="bg-white/70 border border-brand-ink/5 rounded-3xl p-6 sm:p-8 space-y-4">
      <?= csrf_field() ?>
      <input type="email" name="email" required placeholder="Email" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
      <input type="password" name="password" required placeholder="Password" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm">
      <button class="btn-ink w-full py-3 text-sm tracking-[0.12em] uppercase">Sign In</button>
    </form>
    <p class="text-center text-sm text-brand-soft mt-6">New here? <a class="underline text-brand-ink" href="<?= e(url('index.php?page=register')) ?>">Create an account</a></p>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
db();

if (current_user() && (current_user()['role'] ?? '') === 'admin') {
    header('Location: index.php');
    exit;
}

$error = null;
if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session.';
    } elseif (attempt_login((string) post('email'), (string) post('password'))) {
        $user = current_user();
        if ($user && $user['role'] === 'admin') {
            header('Location: index.php');
            exit;
        }
        logout_user();
        $error = 'Admin access only.';
    } else {
        $error = 'Invalid credentials.';
    }
}

$storeName = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin Login – <?= htmlspecialchars($storeName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: Outfit, system-ui, sans-serif; }
    .font-display { font-family: 'Cormorant Garamond', Georgia, serif; }
    .password-field { position: relative; }
    .password-field input {
      width: 100%;
      border-radius: 0.75rem;
      border: 1px solid #e7e5e4;
      background: #fff;
      padding: 0.75rem 2.75rem 0.75rem 1rem;
      font-size: 0.875rem;
      outline: none;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .password-field input:focus {
      border-color: #a8a29e;
      box-shadow: 0 0 0 3px rgba(168, 162, 158, 0.25);
    }
    .password-field__toggle {
      position: absolute;
      right: 0.65rem;
      top: 50%;
      transform: translateY(-50%);
      display: inline-flex;
      color: #78716c;
      padding: 0.25rem;
      border-radius: 0.5rem;
    }
    .password-field__toggle:hover { color: #1c1917; }
    .password-field__icon--hide { display: none; }
    .password-field.is-visible .password-field__icon--show { display: none; }
    .password-field.is-visible .password-field__icon--hide { display: block; }
  </style>
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
  <main class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <p class="text-[11px] tracking-[0.22em] uppercase text-stone-400 mb-2">Admin</p>
        <h1 class="font-display text-4xl text-stone-900"><?= htmlspecialchars($storeName) ?></h1>
        <p class="mt-2 text-sm text-stone-500">Sign in to manage your store</p>
      </div>

      <form method="post" class="bg-white border border-stone-200 rounded-2xl p-6 sm:p-7 space-y-4 shadow-sm">
        <?php if ($error): ?>
          <div class="rounded-xl bg-rose-50 border border-rose-100 text-rose-700 text-sm px-3.5 py-2.5" role="alert">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <?= csrf_field() ?>

        <div>
          <label for="admin-email" class="block text-xs font-medium tracking-wide text-stone-600 mb-1.5">Email</label>
          <input
            id="admin-email"
            type="email"
            name="email"
            required
            autocomplete="username"
            value="<?= htmlspecialchars((string) post('email', '')) ?>"
            class="w-full rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm outline-none focus:border-stone-400 focus:ring-2 focus:ring-stone-200"
            placeholder="you@example.com"
          >
        </div>

        <div>
          <label for="admin-password" class="block text-xs font-medium tracking-wide text-stone-600 mb-1.5">Password</label>
          <div class="password-field">
            <input
              id="admin-password"
              type="password"
              name="password"
              required
              autocomplete="current-password"
              data-password-input
              placeholder="••••••••"
            >
            <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
              <svg class="password-field__icon password-field__icon--show" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              <svg class="password-field__icon password-field__icon--hide" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.057 7.51 19 12.001 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L9.88 9.88m8.894 8.894L21 21m-3.228-3.228L14.12 14.12M9.88 9.88a3 3 0 004.24 4.24"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="w-full rounded-xl bg-stone-900 text-white py-3 text-sm font-medium tracking-wide hover:bg-stone-800 transition">
          Sign in
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-stone-400">
        <a href="<?= htmlspecialchars(url('index.php?page=home')) ?>" class="hover:text-stone-700 transition">← Back to store</a>
      </p>
    </div>
  </main>
  <script src="<?= htmlspecialchars(asset('assets/js/app.js')) ?>"></script>
</body>
</html>

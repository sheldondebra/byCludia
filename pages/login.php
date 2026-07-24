<?php
declare(strict_types=1);

$pageTitle = 'Sign In – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$error = null;
$authMode = post('auth_mode', 'email') === 'phone' ? 'phone' : 'email';
$identifier = (string) post($authMode === 'phone' ? 'phone' : 'email', '');

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session.';
    } elseif (attempt_login($identifier, (string) post('password'), $authMode)) {
        $user = current_user();
        if ($user && $user['role'] === 'admin') {
            redirect('admin/index.php');
        }
        flash('success', 'Welcome back!');
        redirect('index.php?page=account');
    } else {
        $error = $authMode === 'phone'
            ? 'Invalid phone number or password.'
            : 'Invalid email or password.';
    }
}

$authImage = 'assets/images/products/wp/Claudia.jpg';
$authEyebrow = 'Welcome back';
$authTitle = 'Sign in';
$authLead = 'Access your orders, wishlist, and loyalty points.';

require ROOT_PATH . '/includes/header.php';
?>

<section class="auth-stage" data-auth-form>
  <div class="auth-stage__grid">
    <aside class="auth-stage__visual" aria-hidden="true">
      <img
        src="<?= e(asset($authImage)) ?>"
        alt=""
        class="auth-stage__image"
        loading="eager"
      >
      <div class="auth-stage__wash"></div>
      <div class="auth-stage__caption">
        <p class="auth-stage__brand">Hair by Claudia Darlene</p>
        <p class="auth-stage__tagline">Luxury hair for every curl story.</p>
      </div>
    </aside>

    <div class="auth-stage__panel">
      <div class="auth-stage__content">
        <p class="auth-stage__eyebrow"><?= e($authEyebrow) ?></p>
        <h1 class="auth-stage__title font-display"><?= e($authTitle) ?></h1>
        <p class="auth-stage__lead"><?= e($authLead) ?></p>

        <?php if ($error): ?>
          <div class="auth-alert" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="auth-toggle" role="tablist" aria-label="Sign in with">
          <button type="button" class="auth-toggle__btn<?= $authMode === 'email' ? ' is-active' : '' ?>" data-auth-mode="email" role="tab" aria-selected="<?= $authMode === 'email' ? 'true' : 'false' ?>">Email</button>
          <button type="button" class="auth-toggle__btn<?= $authMode === 'phone' ? ' is-active' : '' ?>" data-auth-mode="phone" role="tab" aria-selected="<?= $authMode === 'phone' ? 'true' : 'false' ?>">Phone</button>
        </div>

        <form method="post" class="auth-form">
          <?= csrf_field() ?>
          <input type="hidden" name="auth_mode" value="<?= e($authMode) ?>" data-auth-mode-input>

          <div class="auth-field" data-auth-panel="email"<?= $authMode === 'phone' ? ' hidden' : '' ?>>
            <label for="login-email">Email</label>
            <input
              id="login-email"
              type="email"
              name="email"
              autocomplete="email"
              placeholder="you@example.com"
              value="<?= $authMode === 'email' ? e($identifier) : '' ?>"
              <?= $authMode === 'email' ? 'required' : '' ?>
              class="auth-input"
            >
          </div>

          <div class="auth-field" data-auth-panel="phone"<?= $authMode === 'email' ? ' hidden' : '' ?>>
            <label for="login-phone">Phone number</label>
            <input
              id="login-phone"
              type="tel"
              name="phone"
              autocomplete="tel"
              inputmode="tel"
              placeholder="+44 7342 590296"
              value="<?= $authMode === 'phone' ? e($identifier) : '' ?>"
              <?= $authMode === 'phone' ? 'required' : '' ?>
              class="auth-input"
            >
          </div>

          <div class="auth-field">
            <label for="login-password">Password</label>
            <div class="password-field">
              <input
                id="login-password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Your password"
                class="auth-input"
                data-password-input
              >
              <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                <svg class="password-field__icon password-field__icon--show" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg class="password-field__icon password-field__icon--hide" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.057 7.51 19 12.001 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L9.88 9.88m8.894 8.894L21 21m-3.228-3.228L14.12 14.12M9.88 9.88a3 3 0 004.24 4.24"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-ink auth-submit">Sign In</button>
        </form>

        <p class="auth-switch">
          New here?
          <a href="<?= e(url('index.php?page=register')) ?>">Create an account</a>
        </p>
      </div>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

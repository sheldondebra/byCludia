<?php
declare(strict_types=1);

$pageTitle = 'Create Account – Hair by Claudia Darlene';
$robots = 'noindex, nofollow';
$error = null;
$authMode = post('auth_mode', 'email') === 'phone' ? 'phone' : 'email';
$name = (string) post('name', '');
$email = (string) post('email', '');
$phone = (string) post('phone', '');

if (request_method() === 'POST') {
    $password = (string) post('password');
    $passwordConfirm = (string) post('password_confirm');

    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = register_user(
            $name,
            $password,
            $authMode === 'email' ? $email : null,
            $authMode === 'phone' ? $phone : null
        );
        if ($result['ok']) {
            flash('success', 'Account created. Welcome!');
            redirect('index.php?page=account');
        }
        $error = $result['error'] ?? 'Registration failed.';
    }
}

$authImage = 'assets/images/products/wp/Facetune_16-07-2025-09-34-33-1-scaled.jpg';
$authEyebrow = 'Join the studio';
$authTitle = 'Create account';
$authLead = 'Sign up with email or phone — whichever you prefer.';

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
        <p class="auth-stage__tagline">Start your curl story with us.</p>
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

        <div class="auth-toggle" role="tablist" aria-label="Sign up with">
          <button type="button" class="auth-toggle__btn<?= $authMode === 'email' ? ' is-active' : '' ?>" data-auth-mode="email" role="tab" aria-selected="<?= $authMode === 'email' ? 'true' : 'false' ?>">Email</button>
          <button type="button" class="auth-toggle__btn<?= $authMode === 'phone' ? ' is-active' : '' ?>" data-auth-mode="phone" role="tab" aria-selected="<?= $authMode === 'phone' ? 'true' : 'false' ?>">Phone</button>
        </div>

        <form method="post" class="auth-form">
          <?= csrf_field() ?>
          <input type="hidden" name="auth_mode" value="<?= e($authMode) ?>" data-auth-mode-input>

          <div class="auth-field">
            <label for="register-name">Full name</label>
            <input
              id="register-name"
              type="text"
              name="name"
              required
              autocomplete="name"
              placeholder="Your name"
              value="<?= e($name) ?>"
              class="auth-input"
            >
          </div>

          <div class="auth-field" data-auth-panel="email"<?= $authMode === 'phone' ? ' hidden' : '' ?>>
            <label for="register-email">Email</label>
            <input
              id="register-email"
              type="email"
              name="email"
              autocomplete="email"
              placeholder="you@example.com"
              value="<?= e($email) ?>"
              <?= $authMode === 'email' ? 'required' : '' ?>
              class="auth-input"
            >
          </div>

          <div class="auth-field" data-auth-panel="phone"<?= $authMode === 'email' ? ' hidden' : '' ?>>
            <label for="register-phone">Phone number</label>
            <input
              id="register-phone"
              type="tel"
              name="phone"
              autocomplete="tel"
              inputmode="tel"
              placeholder="+44 7342 590296"
              value="<?= e($phone) ?>"
              <?= $authMode === 'phone' ? 'required' : '' ?>
              class="auth-input"
            >
            <p class="auth-hint">We’ll use this to sign you in. Email can be added later at checkout.</p>
          </div>

          <div class="auth-field">
            <label for="register-password">Password</label>
            <div class="password-field">
              <input
                id="register-password"
                type="password"
                name="password"
                required
                minlength="8"
                autocomplete="new-password"
                placeholder="At least 8 characters"
                class="auth-input"
                data-password-input
                data-password-strength-source
              >
              <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                <svg class="password-field__icon password-field__icon--show" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg class="password-field__icon password-field__icon--hide" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.057 7.51 19 12.001 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L9.88 9.88m8.894 8.894L21 21m-3.228-3.228L14.12 14.12M9.88 9.88a3 3 0 004.24 4.24"/></svg>
              </button>
            </div>
            <div class="password-strength" data-password-strength hidden>
              <div class="password-strength__track" aria-hidden="true">
                <span class="password-strength__bar" data-password-strength-bar></span>
              </div>
              <p class="password-strength__label" data-password-strength-label></p>
            </div>
          </div>

          <div class="auth-field">
            <label for="register-password-confirm">Confirm password</label>
            <div class="password-field">
              <input
                id="register-password-confirm"
                type="password"
                name="password_confirm"
                required
                minlength="8"
                autocomplete="new-password"
                placeholder="Re-enter password"
                class="auth-input"
                data-password-input
                data-password-confirm
              >
              <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                <svg class="password-field__icon password-field__icon--show" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg class="password-field__icon password-field__icon--hide" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.057 7.51 19 12.001 19c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L9.88 9.88m8.894 8.894L21 21m-3.228-3.228L14.12 14.12M9.88 9.88a3 3 0 004.24 4.24"/></svg>
              </button>
            </div>
            <p class="auth-hint auth-hint--error" data-password-match-msg hidden>Passwords do not match.</p>
          </div>

          <button type="submit" class="btn-ink auth-submit">Create Account</button>
        </form>

        <p class="auth-switch">
          Already have an account?
          <a href="<?= e(url('index.php?page=login')) ?>">Sign in</a>
        </p>
      </div>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

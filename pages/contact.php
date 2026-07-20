<?php
declare(strict_types=1);

$pageTitle = 'Contact – Hair by Claudia Darlene';
$pageDescription = 'Get in touch with Hair by Claudia Darlene for orders, styling advice, and support.';
$canonical = url('contact');
$sent = false;
$error = null;

if (request_method() === 'POST') {
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $name = trim((string) post('name'));
        $email = trim((string) post('email'));
        $subject = trim((string) post('subject'));
        $message = trim((string) post('message'));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
            $error = 'Please fill in all required fields with a valid email.';
        } else {
            // Email the store if SMTP is configured (fails silently otherwise).
            $to = (string) setting('contact_email', '');
            if ($to !== '' && function_exists('mailer_enabled') && mailer_enabled()) {
                $html = '<h3>New contact message</h3>'
                    . '<p><strong>Name:</strong> ' . e($name) . '</p>'
                    . '<p><strong>Email:</strong> ' . e($email) . '</p>'
                    . '<p><strong>Subject:</strong> ' . e($subject ?: '—') . '</p>'
                    . '<p><strong>Message:</strong><br>' . nl2br(e($message)) . '</p>';
                @send_mail($to, 'Contact form: ' . ($subject ?: 'New message'), $html);
            }
            $sent = true;
        }
    }
}

$cEmail = (string) setting('contact_email', 'info@byclaudiadarlene.com');
$cPhone = (string) setting('contact_phone', '+44 7342 590296');
$cAddress = (string) setting('contact_address', '');
$wa = preg_replace('/[^0-9]/', '', $cPhone);
$ig = (string) setting('social_instagram', '');
$tk = (string) setting('social_tiktok', '');
$fb = (string) setting('social_facebook', '');

require ROOT_PATH . '/includes/header.php';
?>

<section class="bg-brand-mist/60 border-b border-brand-ink/5">
  <div class="max-w-3xl mx-auto px-6 py-16 sm:py-20 text-center">
    <p class="text-[11px] tracking-[0.22em] uppercase text-brand-blushDeep mb-3">We're here for you</p>
    <h1 class="font-display text-4xl sm:text-6xl mb-4">Get in touch</h1>
    <p class="text-brand-soft max-w-xl mx-auto">Questions about an order, texture matching, or styling? Our team of specialists would love to help — reach us any way you like.</p>
  </div>
</section>

<section class="py-14 sm:py-20">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-[1fr_1.2fr] gap-8 lg:gap-12 items-start">

    <!-- Contact details -->
    <div class="space-y-4">
      <a href="mailto:<?= e($cEmail) ?>" class="flex items-start gap-4 rounded-2xl bg-white border border-brand-ink/5 p-5 shadow-soft/50 hover:border-brand-ink/15 transition">
        <span class="shrink-0 w-11 h-11 rounded-full bg-brand-mist text-brand-ink flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg>
        </span>
        <span>
          <span class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-0.5">Email</span>
          <span class="block text-brand-ink font-medium break-all"><?= e($cEmail) ?></span>
        </span>
      </a>

      <a href="tel:<?= e(preg_replace('/\s+/', '', $cPhone)) ?>" class="flex items-start gap-4 rounded-2xl bg-white border border-brand-ink/5 p-5 shadow-soft/50 hover:border-brand-ink/15 transition">
        <span class="shrink-0 w-11 h-11 rounded-full bg-brand-mist text-brand-ink flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5c0 8.28 6.72 15 15 15h1a2 2 0 002-2v-2.5a1 1 0 00-.76-.97l-4-1a1 1 0 00-1.06.45l-1 1.6a12.5 12.5 0 01-5.3-5.3l1.6-1a1 1 0 00.45-1.06l-1-4A1 1 0 008.5 3H6a2 2 0 00-2 2z"/></svg>
        </span>
        <span>
          <span class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-0.5">Phone</span>
          <span class="block text-brand-ink font-medium"><?= e($cPhone) ?></span>
        </span>
      </a>

      <?php if ($wa !== ''): ?>
        <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" class="flex items-start gap-4 rounded-2xl bg-white border border-brand-ink/5 p-5 shadow-soft/50 hover:border-brand-ink/15 transition">
          <span class="shrink-0 w-11 h-11 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.13c-.24.68-1.42 1.31-1.95 1.36-.5.05-1.13.24-3.72-.78-3.13-1.24-5.13-4.42-5.29-4.63-.15-.2-1.26-1.68-1.26-3.2 0-1.53.8-2.28 1.08-2.59.28-.31.61-.38.81-.38.2 0 .41 0 .58.01.19.01.44-.07.68.52.24.6.83 2.06.9 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.35 1.45.29.15.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.39-.24.65-.15.27.1 1.71.81 2 .96.29.15.49.22.56.34.07.12.07.68-.17 1.36z"/></svg>
          </span>
          <span>
            <span class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-0.5">WhatsApp</span>
            <span class="block text-brand-ink font-medium">Chat with us</span>
          </span>
        </a>
      <?php endif; ?>

      <?php if ($cAddress !== ''): ?>
        <div class="flex items-start gap-4 rounded-2xl bg-white border border-brand-ink/5 p-5 shadow-soft/50">
          <span class="shrink-0 w-11 h-11 rounded-full bg-brand-mist text-brand-ink flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-7-10a7 7 0 0114 0c0 5.65-7 10-7 10z"/><circle cx="12" cy="11" r="2.5"/></svg>
          </span>
          <span>
            <span class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-0.5">Address</span>
            <span class="block text-brand-ink font-medium"><?= e($cAddress) ?></span>
          </span>
        </div>
      <?php endif; ?>

      <?php if ($ig || $tk || $fb): ?>
        <div class="rounded-2xl bg-white border border-brand-ink/5 p-5 shadow-soft/50">
          <span class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-3">Follow us</span>
          <div class="flex gap-2">
            <?php if ($ig): ?><a href="<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram" class="w-10 h-10 rounded-full bg-brand-mist hover:bg-brand-ink hover:text-white flex items-center justify-center transition"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.21 15.58 2.2 15.2 2.2 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.21 8.8 2.2 12 2.2zm0 3.3a6.5 6.5 0 100 13 6.5 6.5 0 000-13zm0 10.72a4.22 4.22 0 110-8.44 4.22 4.22 0 010 8.44zm6.75-10.96a1.52 1.52 0 11-3.04 0 1.52 1.52 0 013.04 0z"/></svg></a><?php endif; ?>
            <?php if ($tk): ?><a href="<?= e($tk) ?>" target="_blank" rel="noopener" aria-label="TikTok" class="w-10 h-10 rounded-full bg-brand-mist hover:bg-brand-ink hover:text-white flex items-center justify-center transition"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 3c.3 2.1 1.5 3.6 3.5 3.9v2.5c-1.2.1-2.4-.2-3.5-.8v5.9a5.6 5.6 0 11-5.6-5.6c.3 0 .6 0 .9.1v2.7a2.9 2.9 0 00-.9-.1 2.9 2.9 0 102.9 2.9V3h2.7z"/></svg></a><?php endif; ?>
            <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook" class="w-10 h-10 rounded-full bg-brand-mist hover:bg-brand-ink hover:text-white flex items-center justify-center transition"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.9 3.78-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.9h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94z"/></svg></a><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="bg-white border border-brand-ink/5 rounded-[28px] p-6 sm:p-10 shadow-soft">
      <h2 class="font-display text-3xl mb-2">Send us a message</h2>
      <p class="text-sm text-brand-soft mb-6">We usually reply within one business day.</p>

      <?php if ($sent): ?>
        <div class="rounded-2xl bg-emerald-50 text-emerald-800 px-4 py-4 text-sm mb-6 flex items-center gap-2">
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Thank you! Your message has been received — we'll be in touch soon.
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-50 text-rose-800 px-4 py-3 text-sm mb-6"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <?= csrf_field() ?>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-1.5">Name *</label>
            <input name="name" required value="<?= e((string) post('name')) ?>" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
          </div>
          <div>
            <label class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-1.5">Email *</label>
            <input name="email" type="email" required value="<?= e((string) post('email')) ?>" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
          </div>
        </div>
        <div>
          <label class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-1.5">Subject</label>
          <input name="subject" value="<?= e((string) post('subject')) ?>" placeholder="Order enquiry, texture advice…" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush">
        </div>
        <div>
          <label class="block text-xs tracking-[0.14em] uppercase text-brand-soft mb-1.5">Message *</label>
          <textarea name="message" required rows="6" placeholder="How can we help?" class="w-full rounded-2xl border border-brand-ink/10 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-blush"><?= e((string) post('message')) ?></textarea>
        </div>
        <button class="btn-ink w-full py-3.5 text-sm tracking-[0.14em] uppercase">Send Message</button>
      </form>
    </div>
  </div>
</section>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

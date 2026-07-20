<?php
declare(strict_types=1);
/**
 * Shared layout for legal / policy pages.
 * Expects: $pageTitle, $pageDescription, $policyHeading, $policyIntro (optional), $policySections (HTML string)
 */
$canonical = $canonical ?? url('index.php?page=' . ($_GET['page'] ?? ''));
require ROOT_PATH . '/includes/header.php';
?>

<section class="py-16 sm:py-20">
  <div class="max-w-3xl mx-auto px-6">
    <p class="text-xs tracking-[0.28em] uppercase text-brand-soft mb-3">Hair by Claudia Darlene</p>
    <h1 class="font-display text-4xl sm:text-5xl mb-6"><?= e($policyHeading) ?></h1>
    <?php if (!empty($policyIntro)): ?>
      <p class="text-brand-soft leading-relaxed text-base sm:text-lg mb-10"><?= e($policyIntro) ?></p>
    <?php endif; ?>
    <div class="policy-content space-y-8 text-brand-ink/85 leading-relaxed">
      <?= $policyBody ?>
    </div>
    <div class="mt-12 pt-8 border-t border-brand-ink/10 flex flex-wrap gap-4 text-sm">
      <a class="underline hover:text-brand-ink" href="<?= e(url('index.php?page=returns-policy')) ?>">Returns Policy</a>
      <a class="underline hover:text-brand-ink" href="<?= e(url('index.php?page=privacy-policy')) ?>">Privacy Policy</a>
      <a class="underline hover:text-brand-ink" href="<?= e(url('index.php?page=shipping-policy')) ?>">Shipping Policy</a>
      <a class="underline hover:text-brand-ink" href="<?= e(url('index.php?page=terms')) ?>">Terms &amp; Conditions</a>
      <a class="underline hover:text-brand-ink" href="<?= e(url('index.php?page=contact')) ?>">Contact</a>
    </div>
  </div>
</section>

<style>
  .policy-content h2 {
    font-family: "Cormorant Garamond", Georgia, serif;
    font-size: 1.75rem;
    margin-bottom: 0.75rem;
    color: #1C1917;
  }
  .policy-content h3 {
    font-family: "Cormorant Garamond", Georgia, serif;
    font-size: 1.35rem;
    margin-bottom: 0.5rem;
    color: #1C1917;
  }
  .policy-content p + p { margin-top: 0.85rem; }
  .policy-content ul {
    list-style: disc;
    padding-left: 1.25rem;
    margin-top: 0.75rem;
    display: grid;
    gap: 0.4rem;
  }
  .policy-content a { text-decoration: underline; }
</style>

<?php require ROOT_PATH . '/includes/footer.php'; ?>

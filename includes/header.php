<?php
declare(strict_types=1);
/** @var array $config */
$pageTitle = $pageTitle ?? $config['app_name'];
$pageDescription = $pageDescription ?? setting('meta_description', 'Luxury hair for every curl story.');
$logoPath = (string) setting('logo_path', 'assets/images/logo.png');

// --- SEO defaults (pages may override before including this file) ---
$siteName = setting('store_name', $config['app_name']);
$canonical = $canonical ?? current_url();
$robots = $robots ?? 'index, follow';
$ogType = $ogType ?? 'website';
$ogImage = isset($ogImage) ? $ogImage : (string) setting('og_image', $logoPath);
$ogImageUrl = str_starts_with((string) $ogImage, 'http') ? (string) $ogImage : asset((string) $ogImage);
$ogImageAlt = $ogImageAlt ?? $siteName;
$pageJsonLd = $jsonLd ?? null;
$user = current_user();
$cartCount = cart_count();
$cartTotal = money(cart_subtotal_gbp());
$wishlistCount = wishlist_count();
$currencies = currency_rates();
$activeCurrency = current_currency();
$promo = setting('promo_banner', 'Worldwide Shipping Available | Klarna & Clearpay | Checkout in your currency');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="robots" content="<?= e($robots) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <meta name="theme-color" content="#F3C4C4">

  <!-- Open Graph -->
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDescription) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImageUrl) ?>">
  <meta property="og:image:alt" content="<?= e((string) $ogImageAlt) ?>">
  <meta property="og:locale" content="en_GB">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($pageDescription) ?>">
  <meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
  <meta name="twitter:image:alt" content="<?= e((string) $ogImageAlt) ?>">

  <link rel="icon" type="image/png" href="<?= e(asset($logoPath)) ?>">
  <link rel="apple-touch-icon" href="<?= e(asset($logoPath)) ?>">

  <!-- Sitewide structured data -->
  <?= json_ld(seo_organization_jsonld()) ?>
  <?= json_ld([
      '@context' => 'https://schema.org',
      '@type' => 'WebSite',
      'name' => $siteName,
      'url' => url(),
      'potentialAction' => [
          '@type' => 'SearchAction',
          'target' => rtrim(url('shop'), '/') . '?q={search_term_string}',
          'query-input' => 'required name=search_term_string',
      ],
  ]) ?>
  <?php if ($pageJsonLd): ?><?= is_string($pageJsonLd) ? $pageJsonLd : json_ld($pageJsonLd) ?><?php endif; ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              blush: '#F3C4C4',
              blushDeep: '#E8A8A8',
              cream: '#FBF7F2',
              mist: '#F7EDE8',
              ink: '#1C1917',
              soft: '#8A7A76'
            }
          },
          fontFamily: {
            display: ['"Cormorant Garamond"', 'Georgia', 'serif'],
            sans: ['Outfit', 'system-ui', 'sans-serif']
          },
          boxShadow: {
            soft: '0 18px 50px rgba(28, 25, 23, 0.08)'
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="bg-brand-cream text-brand-ink font-sans antialiased">
  <div class="promo-bar text-center text-[11px] sm:text-xs tracking-[0.14em] uppercase py-2.5 px-4 bg-black text-white">
    <?= e($promo) ?>
  </div>

  <header class="site-header sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-20 sm:h-24 gap-4">
        <nav class="hidden md:flex items-center gap-6 lg:gap-8 text-[12px] tracking-[0.18em] uppercase">
          <a class="nav-link <?= active_nav('home') ?>" href="<?= e(url('index.php?page=home')) ?>">Home</a>
          <a class="nav-link <?= active_nav('shop') ?>" href="<?= e(url('index.php?page=shop')) ?>">Our Collection</a>
          <a class="nav-link <?= active_nav('about') ?>" href="<?= e(url('index.php?page=about')) ?>">Our Story</a>
          <a class="nav-link <?= active_nav('blog') ?>" href="<?= e(url('index.php?page=blog')) ?>">Journal</a>
          <a class="nav-link <?= active_nav('faq') ?>" href="<?= e(url('index.php?page=faq')) ?>">Care</a>
        </nav>

        <button id="mobile-menu-btn" class="md:hidden p-2 -ml-2 rounded-full hover:bg-brand-blush/40 transition" aria-label="Open menu">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <a href="<?= e(url('index.php?page=home')) ?>" class="absolute left-1/2 -translate-x-1/2 text-center brand-mark py-2 px-4 sm:px-6 transition-transform duration-300 hover:scale-[1.03]">
          <img src="<?= e(asset($logoPath)) ?>" alt="<?= e(setting('store_name', 'By Claudia Darlene')) ?>" class="h-14 sm:h-16 w-auto object-contain">
        </a>

        <div class="flex items-center gap-0.5 sm:gap-1 ml-auto">
          <div class="hidden sm:block relative mr-1">
            <label class="sr-only" for="currency">Currency</label>
            <select id="currency" name="currency" onchange="window.location='<?= e(url('api/currency.php')) ?>?code='+this.value" class="appearance-none bg-white/60 hover:bg-white text-[11px] font-medium tracking-[0.12em] border border-brand-ink/10 rounded-full pl-3.5 pr-7 py-1.5 cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-brand-blushDeep/40">
              <?php foreach ($currencies as $code => $c): ?>
                <option value="<?= e($code) ?>" <?= $code === $activeCurrency ? 'selected' : '' ?>><?= e($code) ?></option>
              <?php endforeach; ?>
            </select>
            <svg class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-brand-ink/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <a href="<?= e(url('index.php?page=compare')) ?>" class="relative p-2.5 rounded-full hover:bg-brand-blush/50 transition hidden sm:inline-flex" aria-label="Compare">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h2m6-16h2a2 2 0 012 2v12a2 2 0 01-2 2h-2m-3-18v20"/></svg>
            <span id="compare-count" class="absolute top-0 right-0 min-w-[18px] h-[18px] rounded-full bg-brand-ink text-white text-[10px] flex items-center justify-center px-1 hidden">0</span>
          </a>
          <a href="<?= e(url('index.php?page=wishlist')) ?>" class="relative p-2.5 rounded-full hover:bg-brand-blush/50 transition hidden sm:inline-flex" aria-label="Wishlist">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21C12 21 4 13.9 4 8.8 4 6.1 6.1 4 8.8 4c1.6 0 3.1.8 3.2 2 .1-1.2 1.6-2 3.2-2C17.9 4 20 6.1 20 8.8c0 5.1-8 12.2-8 12.2z"/></svg>
            <span id="wishlist-count" class="absolute top-0 right-0 min-w-[18px] h-[18px] rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center px-1 <?= $wishlistCount > 0 ? '' : 'hidden' ?>"><?= $wishlistCount ?></span>
          </a>
          <a href="<?= e(url('index.php?page=' . ($user ? 'account' : 'login'))) ?>" class="p-2.5 rounded-full hover:bg-brand-blush/50 transition" aria-label="Account">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </a>
          <div class="cart-nav relative ml-1">
            <a href="<?= e(url('index.php?page=cart')) ?>" class="cart-nav__trigger relative flex items-center gap-2 pl-2.5 pr-3.5 py-2 rounded-full bg-brand-ink text-white hover:bg-brand-ink/90 transition" aria-label="Cart" aria-haspopup="true" aria-expanded="false" aria-controls="cart-preview">
              <span class="relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1.4 9.2A2 2 0 0115.62 20H8.38a2 2 0 01-1.98-1.8L5 9z"/></svg>
                <span id="cart-count" class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] rounded-full bg-brand-blushDeep text-brand-ink text-[10px] font-semibold flex items-center justify-center px-1"><?= (int) $cartCount ?></span>
              </span>
              <span id="cart-total" class="hidden lg:inline text-xs font-medium tabular-nums"><?= e($cartTotal) ?></span>
            </a>
            <div id="cart-preview" class="cart-nav__panel" role="region" aria-label="Cart preview">
              <div id="cart-preview-body" class="cart-nav__body">
                <?= cart_preview_html() ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden border-t border-brand-ink/10 bg-brand-blush">
      <div class="px-4 py-4 flex flex-col gap-3 text-sm tracking-[0.14em] uppercase">
        <a href="<?= e(url('index.php?page=home')) ?>">Home</a>
        <a href="<?= e(url('index.php?page=shop')) ?>">Our Collection</a>
        <a href="<?= e(url('index.php?page=about')) ?>">Our Story</a>
        <a href="<?= e(url('index.php?page=blog')) ?>">Journal</a>
        <a href="<?= e(url('index.php?page=faq')) ?>">Care</a>
        <a href="<?= e(url('index.php?page=contact')) ?>">Contact</a>
        <a href="<?= e(url('index.php?page=wishlist')) ?>">Wishlist</a>
        <a href="<?= e(url('index.php?page=compare')) ?>">Compare</a>
        <div class="pt-2">
          <select onchange="window.location='<?= e(url('api/currency.php')) ?>?code='+this.value" class="w-full bg-white/50 border border-brand-ink/10 rounded-full px-4 py-2 text-xs">
            <?php foreach ($currencies as $code => $c): ?>
              <option value="<?= e($code) ?>" <?= $code === $activeCurrency ? 'selected' : '' ?>><?= e($c['name'] . ' (' . $code . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </header>

  <?php
    $flashToasts = [];
    if ($msg = flash('success')) {
        $flashToasts[] = ['type' => 'success', 'message' => $msg];
    }
    if ($msg = flash('error')) {
        $flashToasts[] = ['type' => 'error', 'message' => $msg];
    }
  ?>

  <main>

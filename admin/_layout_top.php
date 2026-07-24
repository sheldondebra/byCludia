<?php
declare(strict_types=1);
$user = current_user();
$adminQ = trim((string) get('q', ''));
$adminName = trim((string) ($user['name'] ?? 'Admin'));
$adminEmail = trim((string) ($user['email'] ?? ''));
$adminInitials = '';
foreach (preg_split('/\s+/', $adminName) ?: [] as $part) {
    if ($part === '') {
        continue;
    }
    $ch = function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
    $adminInitials .= strtoupper($ch);
    if (strlen($adminInitials) >= 2) {
        break;
    }
}
if ($adminInitials === '') {
    $adminInitials = 'A';
}
$storeName = setting('store_name', 'By Claudia Darlene') ?: 'By Claudia Darlene';
$adminPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – Claudia Darlene</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: Outfit, system-ui, sans-serif; }
    .font-display { font-family: 'Cormorant Garamond', Georgia, serif; }
  </style>
</head>
<body class="bg-[#FBF7F2] text-stone-900 min-h-screen">
  <div class="flex min-h-screen">
    <aside class="w-60 bg-stone-900 text-white p-6 hidden md:flex md:flex-col">
      <p class="font-display text-2xl mb-8 flex items-center gap-2 shrink-0"><?= admin_icon('sparkles', 'w-5 h-5 text-[#F3C4C4]') ?> CD Admin</p>
      <nav class="space-y-1 text-sm flex-1 overflow-y-auto">
        <a class="<?= admin_active_nav('index.php') ?>" href="index.php"><?= admin_icon('layout-dashboard') ?> Dashboard</a>
        <a class="<?= admin_active_nav('products.php') ?>" href="products.php"><?= admin_icon('package') ?> Products</a>
        <a class="<?= admin_active_nav('categories.php') ?>" href="categories.php"><?= admin_icon('folder-tree') ?> Categories</a>
        <a class="<?= admin_active_nav('orders.php') ?>" href="orders.php"><?= admin_icon('receipt-text') ?> Orders</a>
        <a class="<?= admin_active_nav('customers.php') ?>" href="customers.php"><?= admin_icon('users') ?> Customers</a>
        <a class="<?= admin_active_nav('email.php') ?>" href="email.php"><?= admin_icon('mail') ?> Email Marketing</a>
        <a class="<?= admin_active_nav('reviews.php') ?>" href="reviews.php"><?= admin_icon('star') ?> Reviews</a>
        <a class="<?= admin_active_nav('coupons.php') ?>" href="coupons.php"><?= admin_icon('ticket-percent') ?> Coupons</a>
        <a class="<?= admin_active_nav('gift-cards.php') ?>" href="gift-cards.php"><?= admin_icon('gift') ?> Gift cards</a>
        <a class="<?= admin_active_nav('subscribers.php') ?>" href="subscribers.php"><?= admin_icon('mail-plus') ?> Subscribers</a>
        <a class="<?= admin_active_nav('transactions.php') ?>" href="transactions.php"><?= admin_icon('credit-card') ?> Transactions</a>
        <a class="<?= admin_active_nav('integrations.php') ?>" href="integrations.php"><?= admin_icon('plug') ?> Integrations</a>
        <a class="<?= admin_active_nav('seo.php') ?>" href="seo.php"><?= admin_icon('search') ?> SEO</a>
        <a class="<?= admin_active_nav('settings.php') ?>" href="settings.php"><?= admin_icon('settings') ?> Settings</a>
        <div class="pt-4 mt-4 border-t border-white/10 space-y-1">
          <a class="flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5" href="../index.php" target="_blank"><?= admin_icon('external-link') ?> View store</a>
          <a class="flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5" href="<?= e(url('logout')) ?>"><?= admin_icon('log-out') ?> Logout</a>
        </div>
      </nav>
      <div class="mt-6 pt-4 border-t border-white/10 shrink-0">
        <div class="flex items-center gap-3 px-1">
          <span class="w-10 h-10 rounded-full bg-[#F3C4C4] text-stone-900 font-semibold text-sm flex items-center justify-center shrink-0"><?= e($adminInitials) ?></span>
          <div class="min-w-0">
            <p class="text-sm font-medium truncate"><?= e($adminName) ?></p>
            <p class="text-xs text-stone-400 truncate"><?= e($adminEmail !== '' ? $adminEmail : 'Administrator') ?></p>
          </div>
        </div>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
      <!-- Top header -->
      <header class="sticky top-0 z-30 bg-[#FBF7F2]/90 backdrop-blur border-b border-stone-200/80">
        <div class="px-4 sm:px-8 py-3.5 flex items-center gap-3 sm:gap-4">
          <div class="md:hidden flex items-center gap-2 shrink-0">
            <span class="w-9 h-9 rounded-full bg-stone-900 text-[#F3C4C4] font-semibold text-xs flex items-center justify-center"><?= e($adminInitials) ?></span>
          </div>

          <form action="search.php" method="get" class="flex-1 max-w-xl relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search', 'w-4 h-4') ?></span>
            <input name="q" value="<?= e($adminQ) ?>" placeholder="Search orders, products, customers…"
              class="w-full rounded-full border border-stone-200 bg-white pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
          </form>

          <div class="flex items-center gap-2 sm:gap-3 shrink-0 ml-auto">
            <a href="../index.php" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-stone-200 bg-white px-3.5 py-2 text-xs text-stone-600 hover:bg-stone-50 transition">
              <?= admin_icon('external-link', 'w-3.5 h-3.5') ?> Store
            </a>
            <a href="email-compose.php" class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-stone-900 text-white px-3.5 py-2 text-xs hover:bg-stone-800 transition">
              <?= admin_icon('mail', 'w-3.5 h-3.5') ?> Email
            </a>

            <div class="relative group">
              <button type="button" class="flex items-center gap-2.5 rounded-full border border-stone-200 bg-white pl-1 pr-3 py-1 hover:bg-stone-50 transition" aria-haspopup="true">
                <span class="w-8 h-8 rounded-full bg-[#F3C4C4] text-stone-900 font-semibold text-xs flex items-center justify-center"><?= e($adminInitials) ?></span>
                <span class="hidden sm:block text-left min-w-0">
                  <span class="block text-sm font-medium truncate max-w-[120px]"><?= e($adminName) ?></span>
                  <span class="block text-[10px] uppercase tracking-wider text-stone-400">Admin</span>
                </span>
                <?= admin_icon('chevron-down', 'w-3.5 h-3.5 text-stone-400 hidden sm:block') ?>
              </button>
              <div class="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 transition absolute right-0 mt-2 w-52 rounded-2xl border border-stone-200 bg-white shadow-lg py-2 z-40">
                <div class="px-4 py-2 border-b border-stone-100">
                  <p class="text-sm font-medium truncate"><?= e($adminName) ?></p>
                  <p class="text-xs text-stone-400 truncate"><?= e($adminEmail !== '' ? $adminEmail : 'Administrator') ?></p>
                </div>
                <a href="settings.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-stone-600 hover:bg-stone-50"><?= admin_icon('settings', 'w-4 h-4') ?> Settings</a>
                <a href="../index.php" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm text-stone-600 hover:bg-stone-50"><?= admin_icon('external-link', 'w-4 h-4') ?> View store</a>
                <a href="<?= e(url('logout')) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50"><?= admin_icon('log-out', 'w-4 h-4') ?> Logout</a>
              </div>
            </div>
          </div>
        </div>

        <div class="md:hidden px-4 pb-3 flex gap-3 text-sm overflow-x-auto">
          <a href="index.php" class="flex items-center gap-1 shrink-0"><?= admin_icon('layout-dashboard') ?> Dashboard</a>
          <a href="products.php" class="flex items-center gap-1 shrink-0"><?= admin_icon('package') ?> Products</a>
          <a href="orders.php" class="flex items-center gap-1 shrink-0"><?= admin_icon('receipt-text') ?> Orders</a>
          <a href="email.php" class="flex items-center gap-1 shrink-0"><?= admin_icon('mail') ?> Email</a>
          <a href="customers.php" class="flex items-center gap-1 shrink-0"><?= admin_icon('users') ?> Customers</a>
          <a href="settings.php" class="flex items-center gap-1 shrink-0"><?= admin_icon('settings') ?> Settings</a>
        </div>
      </header>

      <main class="flex-1 p-6 sm:p-10">

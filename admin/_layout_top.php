<?php
declare(strict_types=1);
$user = current_user();
$adminQ = trim((string) get('q', ''));
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
    <aside class="w-60 bg-stone-900 text-white p-6 hidden md:block">
      <p class="font-display text-2xl mb-8 flex items-center gap-2"><?= admin_icon('sparkles', 'w-5 h-5 text-[#F3C4C4]') ?> CD Admin</p>
      <nav class="space-y-1 text-sm">
        <a class="<?= admin_active_nav('index.php') ?>" href="index.php"><?= admin_icon('layout-dashboard') ?> Dashboard</a>
        <a class="<?= admin_active_nav('products.php') ?>" href="products.php"><?= admin_icon('package') ?> Products</a>
        <a class="<?= admin_active_nav('categories.php') ?>" href="categories.php"><?= admin_icon('folder-tree') ?> Categories</a>
        <a class="<?= admin_active_nav('orders.php') ?>" href="orders.php"><?= admin_icon('receipt-text') ?> Orders</a>
        <a class="<?= admin_active_nav('customers.php') ?>" href="customers.php"><?= admin_icon('users') ?> Customers</a>
        <a class="<?= admin_active_nav('reviews.php') ?>" href="reviews.php"><?= admin_icon('star') ?> Reviews</a>
        <a class="<?= admin_active_nav('coupons.php') ?>" href="coupons.php"><?= admin_icon('ticket-percent') ?> Coupons</a>
        <a class="<?= admin_active_nav('gift-cards.php') ?>" href="gift-cards.php"><?= admin_icon('gift') ?> Gift cards</a>
        <a class="<?= admin_active_nav('subscribers.php') ?>" href="subscribers.php"><?= admin_icon('mail-plus') ?> Subscribers</a>
        <a class="<?= admin_active_nav('transactions.php') ?>" href="transactions.php"><?= admin_icon('credit-card') ?> Transactions</a>
        <a class="<?= admin_active_nav('integrations.php') ?>" href="integrations.php"><?= admin_icon('plug') ?> Integrations</a>
        <a class="<?= admin_active_nav('settings.php') ?>" href="settings.php"><?= admin_icon('settings') ?> Settings</a>
        <div class="pt-4 mt-4 border-t border-white/10 space-y-1">
          <a class="flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5" href="../index.php" target="_blank"><?= admin_icon('external-link') ?> View store</a>
          <a class="flex items-center gap-3 rounded-lg px-3 py-2 text-stone-300 hover:text-white hover:bg-white/5" href="../index.php?page=logout"><?= admin_icon('log-out') ?> Logout</a>
        </div>
      </nav>
    </aside>
    <main class="flex-1 p-6 sm:p-10">
      <div class="md:hidden mb-6 flex gap-4 text-sm overflow-x-auto">
        <a href="index.php" class="flex items-center gap-1"><?= admin_icon('layout-dashboard') ?> Dashboard</a>
        <a href="products.php" class="flex items-center gap-1"><?= admin_icon('package') ?> Products</a>
        <a href="categories.php" class="flex items-center gap-1"><?= admin_icon('folder-tree') ?> Categories</a>
        <a href="orders.php" class="flex items-center gap-1"><?= admin_icon('receipt-text') ?> Orders</a>
        <a href="customers.php" class="flex items-center gap-1"><?= admin_icon('users') ?> Customers</a>
        <a href="reviews.php" class="flex items-center gap-1"><?= admin_icon('star') ?> Reviews</a>
        <a href="coupons.php" class="flex items-center gap-1"><?= admin_icon('ticket-percent') ?> Coupons</a>
        <a href="gift-cards.php" class="flex items-center gap-1"><?= admin_icon('gift') ?> Gift cards</a>
        <a href="subscribers.php" class="flex items-center gap-1"><?= admin_icon('mail-plus') ?> Subscribers</a>
        <a href="transactions.php" class="flex items-center gap-1"><?= admin_icon('credit-card') ?> Transactions</a>
        <a href="integrations.php" class="flex items-center gap-1"><?= admin_icon('plug') ?> Integrations</a>
        <a href="settings.php" class="flex items-center gap-1"><?= admin_icon('settings') ?> Settings</a>
      </div>

      <form action="search.php" method="get" class="mb-8 max-w-xl relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400"><?= admin_icon('search') ?></span>
        <input name="q" value="<?= e($adminQ) ?>" placeholder="Search orders, products, customers…"
          class="w-full rounded-full border border-stone-200 bg-white pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#F3C4C4]">
      </form>

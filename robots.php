<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
?>
User-agent: *
Allow: /

Disallow: /admin/
Disallow: /api/
Disallow: /config/
Disallow: /includes/
Disallow: /vendor/
Disallow: /*?*page=cart
Disallow: /*?*page=checkout
Disallow: /*?*page=checkout-return
Disallow: /*?*page=account
Disallow: /*?*page=login
Disallow: /*?*page=register
Disallow: /*?*page=wishlist
Disallow: /*?*page=order-success
Disallow: /cart
Disallow: /checkout

Sitemap: <?= url('sitemap.xml') . "\n" ?>

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
Disallow: /cart
Disallow: /checkout
Disallow: /checkout-return
Disallow: /account
Disallow: /login
Disallow: /register
Disallow: /wishlist
Disallow: /wishlist/
Disallow: /order-success
Disallow: /logout

Sitemap: <?= url('sitemap.xml') . "\n" ?>

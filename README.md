# Hair by Claudia Darlene — Custom Ecommerce

Clean, modern PHP ecommerce store for cPanel hosting.

## Stack

- PHP 8+
- MySQL (production) or SQLite (auto local demo)
- Tailwind CSS (CDN)
- Vanilla JavaScript

## Quick start (local)

```bash
cd byclaudiadarlene
php -S localhost:8080
```

Open http://localhost:8080

SQLite database is created automatically at `database/store.sqlite` with sample products.

## Admin

- URL: `/admin/login.php`
- Email: `admin@byclaudiadarlene.com`
- Password: `Admin123!`

## cPanel deploy

1. Upload all files to `public_html` (or a subdomain folder).
2. Create a MySQL database + user in cPanel.
3. Import `database/schema.sql` then `database/seed.sql` via phpMyAdmin.
4. Copy `config/config.example.php` → `config/config.php` and set DB credentials + payment keys.
5. Set `app_url` to your domain (or leave auto-detect).
6. Ensure PHP 8.0+ and `mod_rewrite` (optional pretty URLs via `.htaccess`).

## Features (MVP)

- Home, Shop, Product (variants), Cart, Checkout
- Multi-currency: GBP, USD, EUR, GHS
- Payments UI: Stripe, PayPal, Klarna, Clearpay (demo completes orders until keys are added)
- Coupon `SUMMER10` (10% off)
- Newsletter, FAQ, About, Contact, Account, Wishlist, Blog shell
- Admin: products, orders, customers, settings, currency rates

## Phase 2

Wire live payment provider APIs, blog CMS polish, loyalty automation, Instagram gallery.
# byCludia

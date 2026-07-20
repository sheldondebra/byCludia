# Admin Dashboard Refresh — Design

**Date:** 2026-07-14
**Status:** Approved (design), pending spec review

## Goal

Transform the basic admin area (plain stat cards + bare tables) into a premium, functional operations panel with icons, search, Chart.js graphs, filters, alerts, and quick actions. Scope: **full admin refresh**. Visual direction: **hybrid** — dark sidebar shell with blush (`#F3C4C4`) accents on a warm off-white (`#FBF7F2`) content area, keeping the existing Cormorant Garamond + Outfit type.

## Constraints

- PHP 8+, no build step. Tailwind via CDN (already used). Chart.js + Lucide icons via CDN.
- Works on both SQLite (local demo) and MySQL (prod). All SQL must be portable — use `strftime` for SQLite / date functions carefully, or aggregate in PHP where dialects diverge.
- Preserve existing auth (`require_admin()`), CSRF (`csrf_field()` / `verify_csrf()`), and routing conventions.
- No new DB tables. Read from existing schema: `products`, `product_variants` (has `stock`), `orders` (`status`, `total`, `currency`, `payment_method`, `created_at`), `order_items` (`product_name`, `quantity`, `line_total`), `users`.

## Architecture

### Shared layout (`admin/_layout_top.php`, `_layout_bottom.php`)
- Rework the sidebar: **Lucide icons** next to each nav item, active-item highlight (blush pill) driven by the current script filename.
- Add Chart.js + Lucide script tags in the layout head/footer so every admin page can use them.
- Keep the mobile top-nav but add icons.
- Introduce small reusable UI helpers in `includes/helpers.php`:
  - `admin_status_badge(string $status): string` — colored pill per order status.
  - `admin_active_nav(string $file): string` — returns active classes when the current script matches.
  - `admin_icon(string $name, string $classes = ''): string` — emits `<i data-lucide="name">` markup.

### Dashboard home (`admin/index.php`)
Data layer computed in PHP (portable across SQLite/MySQL):
1. **Stat cards (4)** with icons + trend vs previous period: Revenue, Orders, Customers, Products (with inactive count / low-stock note).
2. **Alerts**: count of low-stock variants (`stock <= threshold`, threshold = 5) and pending orders (`status = 'pending'`), each linking to a filtered list.
3. **Charts** (Chart.js, fed by PHP-encoded JSON):
   - **Revenue over time** — line/area, last N days grouped by day.
   - **Orders by status** — doughnut.
   - **Top products** — horizontal bar, by summed `line_total` from `order_items`.
   - **Payment methods** — doughnut by `orders.payment_method`.
4. **Date range** selector (7 / 30 / 90 days, default 30) via `?range=` querystring; all metrics + charts respect it.
5. **Recent orders** table restyled with status badges and detail links.

### List pages
- **Products (`admin/products.php`)**: search box (name/category, `?q=`), status badges, stock column (summed variant stock, low-stock highlighted), keep existing add/toggle/delete quick actions with icon buttons.
- **Orders (`admin/orders.php`)**: search (`?q=` order number/email) + status filter (`?status=`), status badges, "View details" link, quick "Mark shipped" action (POST, CSRF-protected).
- **Customers (`admin/customers.php`)**: search (`?q=` name/email), show order count + total spent per customer (subquery/join), detail link.

### Global search
- Top-bar search on every admin page routes to a results view. Implement as `?q=` on each list page (scoped search) plus a combined **`admin/search.php`** that queries orders, products, and customers and links into the respective pages. Keep it simple: `LIKE` queries with limits.

## Data flow

Each page: `bootstrap → require_admin → read filters from $_GET → run parameterized queries → aggregate in PHP → render`. Charts receive data via `json_encode()` into inline `<script>` config. No AJAX needed for v1 (server-rendered on load; date-range change reloads the page).

## Error handling

- All queries parameterized (PDO prepared statements) — no interpolation of user input.
- Empty states for every table/chart ("No data yet").
- Division-by-zero guards on trend percentages (show "—" when previous period is 0).
- Chart containers render even with empty datasets.

## Testing

- Manual verification against local SQLite demo (`php -S localhost:8080`), logged into `/admin`.
- Verify: dashboard loads with all 4 charts, date range switches update numbers, alerts show correct counts, search returns results on each list page, quick actions (mark shipped, toggle active) work with CSRF, badges render per status.
- Confirm no PHP notices/warnings; confirm SQL runs on SQLite (primary local target).

## Out of scope (YAGNI)

- Live AJAX/auto-refresh, CSV export, new DB tables, blog CMS, per-user roles/permissions, real-time notifications.

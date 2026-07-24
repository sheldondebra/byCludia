# Email Marketing Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax.

**Goal:** Ship an in-admin Email Marketing hub with contacts, classy templates, compose/send, and campaign history.

**Architecture:** PHP pages under `admin/email*.php`, shared logic in `includes/email_marketing.php` + `includes/email_templates.php`, tables ensured on DB connect, send via existing `send_mail()`.

**Tech Stack:** PHP, SQLite/MySQL, Tailwind CDN (admin), TinyMCE CDN for rich body, existing SMTP mailer.

## Global Constraints

- Match existing admin visual language (cream, stone, blush, Cormorant/Outfit)
- Reuse `send_mail()` / Integrations SMTP settings
- No new Composer packages
- Immediate send only
- CSRF on all mutating forms

---

### Task 1: Schema + core includes

- [x] Add tables to `database/schema.sql` and SQLite init
- [x] `ensure_email_marketing_schema()` from `db()`
- [x] `includes/email_templates.php` — shells + starters
- [x] `includes/email_marketing.php` — audiences, merge, send loop, assets, unsubscribe
- [x] Require from bootstrap

### Task 2: Admin pages + nav

- [x] Dashboard, contacts, compose, campaigns list/detail
- [x] Nav link + active state for `email*` scripts

### Task 3: Unsubscribe + smoke check

- [x] `pages/unsubscribe.php` + route in `index.php`
- [x] `php -l` on new/changed PHP files

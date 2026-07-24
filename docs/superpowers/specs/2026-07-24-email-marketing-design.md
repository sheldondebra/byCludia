# Email Marketing Hub — Design Spec

**Date:** 2026-07-24  
**Status:** Approved — implement

## Goal

Admin Email Marketing hub to compose and send classy branded emails (bulk, coupon, discount, holiday, failed-order) with images, unified contacts from subscribers/orders/users, and campaign history. Immediate send via existing SMTP.

## Decisions

- Full hub in-app (not ESP)
- Immediate send (no cron queue)
- Template starters + freeform rich editor
- Failed-order targets: admin chooses each time
- Contacts: unified directory + source checkboxes at compose time

## Admin screens

- `email.php` — dashboard
- `email-contacts.php` — unified contacts
- `email-compose.php` — compose / send
- `email-campaigns.php` — history list
- `email-campaign.php` — detail + recipient log

## Templates

Promo, Coupon, Discount, Holiday, Failed order — classy cream/serif shell, ~600px, blush accent, merge tags `{{name}}`, `{{email}}`, `{{store_name}}`, `{{coupon_code}}`, `{{order_number}}`.

## Data

- `email_campaigns`, `email_campaign_recipients`, `email_assets`, `email_unsubscribes`
- Live audience query from subscribers + users + orders (deduped)
- Public unsubscribe page

## Out of scope (v1)

Open/click tracking, scheduled sends, background queues, A/B tests, external ESP.

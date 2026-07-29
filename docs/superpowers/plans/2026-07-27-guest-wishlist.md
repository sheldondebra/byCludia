# Guest Wishlist Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow guests to save favourites in session, use `/wishlist`, and merge into account on login/register.

**Architecture:** Session `$_SESSION['wishlist']` product ID list + shared helpers in `includes/wishlist.php`. Logged-in users keep DB `wishlists` rows. Merge on auth success.

**Tech Stack:** PHP sessions, existing SQLite/MySQL `wishlists` table, storefront JS toggle.

## Global Constraints

- Guest cap: 50 products
- No share UI for guests
- Union merge on login/register (never wipe account list)
- No new DB tables

---

### Task 1: Wishlist helpers

**Files:** create `includes/wishlist.php`; require from `includes/bootstrap.php`

- [ ] Add `guest_wishlist_ids`, `wishlist_count`, `wishlist_has`, `wishlist_toggle`, `wishlist_products`, `wishlist_merge_session_into_user`
- [ ] Cap guest list at 50

### Task 2: API + auth merge

**Files:** `api/wishlist.php`, `includes/auth.php`, `assets/js/app.js`

- [ ] API uses `wishlist_toggle`; drop login gate
- [ ] Call merge after login/register
- [ ] Remove JS login redirect for wishlist

### Task 3: UI surfaces

**Files:** `pages/wishlist.php`, `includes/header.php`, `includes/partials/product-card.php`, `pages/product.php`

- [ ] Guest-accessible wishlist page; hide share; soft sign-in CTA
- [ ] Header badge + heart active state via helpers

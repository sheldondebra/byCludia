# Guest Wishlist — Design Spec

**Date:** 2026-07-27  
**Status:** Approved — implemented  
**Approach:** PHP session product-ID list (Approach 1)

## Goal

Let shoppers save favourites without signing in. Guests can toggle hearts and use `/wishlist` to view/remove items. When they later sign in or register, session favourites merge into their account wishlist.

## Decisions

- Guest storage: `$_SESSION['wishlist']` as a list of unique product IDs (integers)
- Guests get a full `/wishlist` page (view + remove); **no share UI** until logged in
- On successful login **and** register: merge session IDs into `wishlists` for that user, then clear the session list
- Logged-in users keep current DB behaviour unchanged
- No new database tables or columns

## Out of scope

- Sharing guest wishlists
- Cross-device guest sync
- Persisting guest list after session expiry / cleared cookies
- Compare-list guest parity (unless already guest-capable)

## Behaviour

### Toggle (product card / product page)

1. `POST /api/wishlist.php` no longer returns `login_required` for guests.
2. If logged in → toggle row in `wishlists` (existing).
3. If guest → add/remove `product_id` in `$_SESSION['wishlist']`.
4. Response still `{ ok, active, count }` so existing JS works; remove the redirect-to-login branch (or leave it unused).

### Wishlist page (`/wishlist`)

1. Remove `require_login()`.
2. **Guest:** load products by session IDs; allow remove (and optional add via POST); hide share block; optional soft CTA to sign in to keep list forever / share.
3. **Logged in:** existing DB list + share UI unchanged.
4. Page remains `noindex, nofollow`.

### Header badge

- Guest count = `count($_SESSION['wishlist'])`.
- Logged-in count = DB count (unchanged).
- Heart active state on cards/product page must read session for guests.

### Merge on auth

Call a helper (e.g. `wishlist_merge_session_into_user(int $userId)`) from:

- `attempt_login()` after setting `$_SESSION['user_id']`
- `register_user()` after setting `$_SESSION['user_id']`

Merge rules:

- For each session product ID that exists and is active (optional: skip inactive), `INSERT OR IGNORE` / `INSERT IGNORE` into `wishlists`
- Clear `$_SESSION['wishlist']`
- Do not delete existing account wishlist rows (union merge)

## Helpers (suggested)

Centralize in `includes/helpers.php` or a small `includes/wishlist.php`:

| Helper | Role |
|--------|------|
| `guest_wishlist_ids(): array` | Normalized unique int IDs from session |
| `wishlist_count(): int` | User DB count or guest session count |
| `wishlist_has(int $productId): bool` | Active heart state |
| `wishlist_toggle(int $productId): array` | Toggle for current auth context; returns `active` + `count` |
| `wishlist_merge_session_into_user(int $userId): void` | Merge + clear session |

Prefer routing API + page through these helpers so logic isn’t duplicated.

## UI notes

- Guest `/wishlist`: same product grid as today when non-empty; empty state can mention signing in is optional.
- Soft banner on guest wishlist: “Sign in to save this list across devices and share it” with link to login (not a hard block).
- No toast forcing login on heart click.

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Invalid / deleted product IDs in session | Filter to existing products when rendering; ignore bad IDs on merge |
| Session size abuse | Cap guest list length (e.g. 50 products); ignore adds beyond cap |
| Logout leaves empty session wishlist | Expected; account list remains in DB |

## Acceptance criteria

- [ ] Guest can add/remove wishlist from product card and product page without login redirect
- [ ] Guest can open `/wishlist`, see saved items, and remove them
- [ ] Guest wishlist has no share controls
- [ ] Header badge updates for guests
- [ ] After login or register, session items appear in account wishlist and session list is cleared
- [ ] Existing logged-in wishlist + share behaviour unchanged

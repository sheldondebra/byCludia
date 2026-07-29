# Flexible Country Shipping — Design Spec

**Date:** 2026-07-27  
**Status:** Implemented  
**Approach:** One shipping price per country (Approach A) + ISO dropdown with Other (C) + free shipping zeroes all rates (A)

## Goal

Let admins set a **default shipping rate** for all destinations and optional **per-country overrides**, managed entirely in admin. Checkout applies override when present and enabled; otherwise the default. Free-shipping threshold still zeroes shipping for every country.

## Decisions

| Topic | Choice |
|-------|--------|
| Pricing model | One rate per country (not per carrier) |
| Country input | ISO country dropdown + **Other** |
| Free shipping | Threshold zeroes default **and** overrides |
| Currency | GBP (same as existing `shipping_flat` / store base) |
| Carriers (DHL/FedEx) | Do not affect customer price in v1; checkout shows a single shipping amount |

## Out of scope (v1)

- Weight / dimensional tiers
- State / province / postcode zones
- Per-carrier country rates
- Multi-currency shipping price lists

## Data model

### Settings (existing)

- `shipping_flat` — Default Shipping Rate (GBP). Rename label in admin to “Default shipping rate”; keep key for compatibility.
- `free_shipping_threshold` — unchanged; `0` / empty = disabled.

### Table `shipping_country_rates`

| Column | Type | Notes |
|--------|------|--------|
| `id` | PK | |
| `country_code` | CHAR(2) UNIQUE | ISO 3166-1 alpha-2 (`ZA`, `GB`, …). Never `XX` for Other. |
| `rate` | DECIMAL/REAL | GBP amount ≥ 0 |
| `is_active` | BOOL/INT | Disabled rules are ignored at checkout |
| `label` | TEXT NULL | Optional admin note (e.g. “Remote / high risk”) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Indexes: unique on `country_code`; lookup index suitable for `(country_code)` where `is_active = 1`.

Ensure table via existing `includes/db.php` schema bootstrap (SQLite + MySQL), same pattern as other tables.

## Rate resolution

```
function shipping_rate_for_country(?string $countryCode, float $subtotalGbp): array
```

1. If free-shipping threshold > 0 and `$subtotalGbp >= threshold` → rate `0`, source `free`.
2. Else if `$countryCode` is a valid ISO code and an **active** row exists → that `rate`, source `override`.
3. Else → `(float) setting('shipping_flat')`, source `default` (covers missing code, **Other**, disabled override).

Return shape (suggested): `['rate' => float, 'source' => 'free'|'override'|'default', 'country_code' => ?string]`.

Checkout **must** recompute on the server when placing the order (never trust client-only totals).

## Checkout UX

- Replace free-text country with `<select>` of ISO countries (name shown, value = code) plus option **Other** (`value=""` or `OTHER`).
- Persist on the order:
  - `shipping_country` — human label (“South Africa” / “Other”)
  - Optionally store code in a new nullable column `shipping_country_code` **or** encode in existing field if we want zero migrations beyond rates table. Prefer adding `shipping_country_code` VARCHAR(2) NULL for clean analytics.
- When country changes, update shipping line + order total via existing checkout JS (extend current ship-radio / summary logic to country change).
- Shipping method UI: show a single line (“Shipping”) with resolved rate, or keep a single standard method whose rate is country-resolved. Do not present competing DHL/FedEx **prices** in v1.

## Admin UI (`/admin/shipping.php`)

Dedicated page (linked from admin nav / settings shipping section):

1. **Defaults** — default rate, free-shipping threshold (save to settings).
2. **Country overrides** — table: country, rate, active, label, actions.
3. **Add / edit** — country select (ISO), rate, active checkbox, optional label. Reject duplicate country codes.
4. **Delete** — confirm; **Disable** — soft off without delete.

No code deploy required to change rates.

### Scalability / performance

- Few dozen overrides typical; unique index on `country_code` keeps lookup O(1).
- Load active rates once per request into a map if needed, or single prepared `SELECT rate FROM … WHERE country_code = ? AND is_active = 1`.
- ISO country list as a static PHP array helper (no external API).

## Files (expected)

| File | Role |
|------|------|
| `includes/db.php` | Create/migrate `shipping_country_rates` (+ optional `orders.shipping_country_code`) |
| `includes/shipping.php` (or helpers) | ISO list, resolve rate, CRUD helpers |
| `admin/shipping.php` | Admin UI |
| `admin/_layout_top.php` (nav) | Link |
| `pages/checkout.php` | Country select + resolve rate |
| `admin/settings.php` | Point “Shipping” to new page or keep defaults synced |

## Acceptance criteria

- [ ] Admin can set default shipping rate
- [ ] Admin can add/edit/delete/enable/disable country overrides without code changes
- [ ] Checkout country dropdown includes ISO list + Other
- [ ] Override applies when country matches and rule is active
- [ ] Otherwise default rate applies (including Other)
- [ ] Free-shipping threshold zeroes shipping for all countries
- [ ] Place-order path computes shipping server-side
- [ ] Disabled override is ignored (falls back to default)

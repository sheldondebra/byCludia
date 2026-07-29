# Country Shipping Implementation Plan

> **For agentic workers:** Implement task-by-task.

**Goal:** Default shipping rate + per-country overrides with admin CRUD and checkout resolution.

**Architecture:** `shipping_country_rates` table; `includes/shipping.php` for ISO list + rate resolve; admin page; checkout country select + server-side rate.

**Tech Stack:** PHP, SQLite/MySQL via existing db bootstrap, admin UI patterns.

## Global Constraints

- One rate per country (GBP)
- ISO dropdown + Other
- Free shipping zeroes all rates
- No per-carrier pricing in v1

---

### Task 1: Schema + shipping helpers
### Task 2: Admin shipping page + nav
### Task 3: Checkout integration
### Task 4: Verify resolution paths

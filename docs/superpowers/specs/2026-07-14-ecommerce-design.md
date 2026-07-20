# By Claudia Darlene — Custom Ecommerce Design

**Date:** 2026-07-14  
**Stack:** PHP + MySQL + HTML + Tailwind CSS + JavaScript (cPanel)

## Goals

Clean, modern, classy ecommerce for Hair by Claudia Darlene. Full custom store replacing WordPress/WooCommerce look, with MVP sellable first and full feature set phased after.

## Decisions

| Decision | Choice |
|----------|--------|
| Architecture | Lightweight custom PHP + MySQL (no Laravel) |
| Payments | Stripe + PayPal + Klarna/Clearpay |
| Currency | GBP, USD, EUR, GHS (base: GBP) |
| Delivery | Phase 1 MVP → Phase 2 extras |
| Hosting | cPanel shared hosting |

## Phase 1 (MVP)

Home, Shop, Product (variants), Cart, Checkout, About, FAQ, Contact, Admin (products, orders, customers, currency rates, settings). Guest checkout. Session cart.

## Phase 2

Blog, wishlist, account dashboard, loyalty/coupons, Instagram gallery, order tracking polish.

## Visual

Blush pink / cream / charcoal. Serif headlines, sans body. Full-bleed hero, generous space, rounded CTAs. Brand-first homepage matching provided UI inspiration.

## Security

PDO prepared statements, password_hash, CSRF, server-only payment secrets, validated uploads, HTTPS on production.

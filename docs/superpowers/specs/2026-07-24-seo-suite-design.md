# Full SEO Suite — Design Spec

**Date:** 2026-07-24  
**Status:** Approved — implementing  
**Approach:** Storefront-native SEO (Approach 2)

## Goal

Ship a full in-app SEO suite so By Claudia Darlene can compete for Google rankings: technical completeness, content fields, and an admin hub with Google-style previews and checklist scores. Builds on existing pretty URLs, meta/OG tags, sitemap/robots, and Product/FAQ/Article schema.

## Decisions

- One SEO helper (`includes/seo.php`) owns title/description/canonical/OG/JSON-LD resolution
- SEO fields live on products, categories, blog posts + global settings (not a separate opaque CMS)
- Admin hub at `/admin/seo.php` for overview, checklist, and deep links to edit entities
- Focus keyword is optional guidance for the checklist — not stuffed into pages automatically
- No third-party SEO SaaS in v1

## 1. Technical SEO

### Meta & social
- Resolve page title via pattern: `{seo_title|entity name} | {store_name}` (configurable pattern in settings)
- Meta description: entity override → page default → global `meta_description`
- Canonical always absolute clean path (`/shop`, `/product/{slug}`, `/privacy`, etc.)
- OG/Twitter image: entity image → `og_image` setting → logo
- Add `og:image:alt` when available

### Structured data
- Keep Organization + WebSite (+ SearchAction)
- Add/normalize:
  - **BreadcrumbList** on product, shop/category, blog post
  - **Product** with Offer, AggregateRating (when reviews exist), image
  - **FAQPage** on FAQ page and product FAQ blocks
  - **Article** / BlogPosting (existing, keep)
  - **LocalBusiness** (or Organization extension) from store address/phone settings when filled
- Emit via existing `json_ld()` helper; pages pass data through `$jsonLd` / SEO helper

### Sitemap & robots
- Expand `sitemap.php`:
  - All indexable static pages (home, shop, about, faq, contact, gift-cards, policies, blog)
  - Products (active only) with optional `<image:image>`
  - Categories with landing URLs
  - Blog posts
  - `lastmod` from `updated_at` / `published_at` when present
- `robots.txt`: keep private areas disallowed; point to sitemap
- Ensure 404 remains `noindex`

### Images & a11y SEO
- Product/card images: meaningful `alt` = product name (or custom `image_alt` if set)
- Prefer WebP uploads already allowed; no forced recompression in v1

## 2. Content SEO

### New / extended fields

**products**
- `seo_title` VARCHAR(70) NULL
- `seo_description` VARCHAR(320) NULL
- `focus_keyword` VARCHAR(80) NULL
- `image_alt` VARCHAR(160) NULL
- `faq_json` TEXT NULL — JSON array of `{question, answer}` (max ~8)

**categories**
- `seo_title`, `seo_description`, `focus_keyword`
- `intro_html` TEXT NULL — landing intro under H1
- Ensure `/shop?category={slug}` or dedicated `/shop/{slug}` — **v1 keeps** `/shop?category=` but title/description/H1/intro become category-aware; optional pretty `/collection/{slug}` alias can follow in v1.1

**blog_posts**
- `seo_title`, `seo_description`, `focus_keyword` (if not already present)

**settings**
- `seo_title_pattern` (default `{page} | {store}`)
- `og_image` (already referenced)
- `seo_default_description` (alias/use existing `meta_description`)
- Optional LocalBusiness: `store_address`, `store_city`, `store_country` (reuse if settings exist)

### Storefront behaviour
- Shop page: when `?category=` present, use category SEO + intro
- Product page: SEO overrides, FAQ accordion + FAQPage schema, breadcrumbs
- Blog post: SEO overrides
- Internal links: keep related products on blog; ensure product page links category + related

## 3. Admin SEO hub

### Nav
- Add **SEO** item in admin sidebar (near Settings)

### `admin/seo.php` — dashboard
- **Site defaults** card: title pattern, default meta description, OG image preview, LocalBusiness fields
- **Google preview** (desktop SERP mock): title (~60 chars), URL, description (~155 chars) for selected entity or homepage
- **Checklist summary**: counts of products/posts missing title, description, image, or failing length rules
- **Inventory table** (tabs: Pages | Products | Categories | Blog):
  - Name, URL, title length, description length, score (0–100), Edit link
- Score rules (simple, transparent):
  - Title present & 30–60 chars: +25
  - Description present & 70–160 chars: +25
  - Indexable image / OG image: +20
  - Focus keyword appears in title or description (if set): +15
  - Schema applicable & present for type: +15
- Quick links: open `/sitemap.xml`, copy sitemap URL for Search Console

### Entity editors
- **Product edit**: SEO section (title, description, focus keyword, image alt, FAQ repeater) + live SERP preview
- **Category edit**: SEO + intro fields (extend `admin/categories.php`)
- **Blog edit** (if admin blog editor exists; else schema + seed fields ready for when it does)

## 4. Shared PHP API

```
seo_page_defaults(array $overrides): array  // title, description, canonical, og_*, robots
seo_score(array $entity): int
seo_product_jsonld(array $product, ...): array
seo_breadcrumbs(array $items): array
ensure_seo_schema(PDO): void                // ALTER TABLE migrations like other ensure_* helpers
```

Header continues to render tags from variables; pages call helper before `header.php`.

## 5. Out of scope (v1)

- Guaranteed #1 rankings (content/backlinks/ads still required)
- Automated keyword research / Ahrefs-style crawl
- hreflang / multi-language
- AMP
- Scheduled SEO audits / email reports
- Forced image CDN or lazy-load rewrite of entire asset pipeline
- Google Search Console OAuth API (manual sitemap submit is enough)

## 6. Success criteria

- Public indexable pages have unique title, description, canonical, OG tags
- Sitemap lists all public products, categories, posts, key static pages with lastmod
- Product + FAQ + Breadcrumb schema validate in Google Rich Results mindset
- Admin can open SEO hub, see scores, edit product SEO, preview SERP snippet
- No regression to pretty URLs or existing checkout/account `noindex` pages

## 7. Implementation order

1. Schema migrations + `includes/seo.php`
2. Wire storefront pages (home, shop/category, product, blog, policies canonicals)
3. Sitemap/robots enrichment
4. Admin product/category SEO fields + SERP preview
5. Admin SEO hub dashboard
6. Smoke-check key URLs + sample rich-results JSON-LD

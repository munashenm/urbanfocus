# Urban Focus — SEO & Ecommerce Optimization Report

**Date:** May 2026  
**Site:** Urban Focus (`urbanfocus.co.za`)  
**Market:** South Africa — IT, networking, security, laptops  
**Competitors referenced:** DBG, FirstShop, Tech.co.za

---

## Executive summary

Urban Focus already had strong foundations: dynamic sitemap, robots.txt, Google Merchant feed, PriceCheck CSV, GTIN support, POPIA/trust pages, blog, and product schema. This pass adds centralized SEO configuration, image sitemaps, pagination SEO, analytics pixels, WhatsApp support, newsletter signup, feed caching, SA keyword optimization, FAQ schema, and recently viewed products.

**Production readiness:** High for technical SEO and Google Shopping. Medium for marketing automation and advanced ecommerce features (reviews, wishlist, abandoned cart).

---

## 1. Technical SEO audit

| Feature | Status | Notes |
|--------|--------|-------|
| XML sitemap | ✅ Implemented | `/sitemap.xml` — auto-generated, cached |
| Image sitemap | ✅ **New** | `/sitemap-images.xml` with product images |
| robots.txt | ✅ Implemented | Dynamic; blocks cart/checkout/admin |
| Canonical URLs | ✅ Implemented | All pages; pagination canonical on shop/category |
| rel prev/next | ✅ **New** | Paginated shop & category listings |
| Open Graph | ✅ Implemented | `en_ZA` locale |
| Twitter/X cards | ✅ Implemented | |
| Product schema | ✅ Enhanced | GTIN/MPN, availability, brand, images |
| Breadcrumb schema | ✅ **New** | Shop & category pages |
| Organization schema | ✅ Implemented | Homepage |
| LocalBusiness schema | ✅ Enhanced | SA cities in `areaServed` |
| FAQ schema | ✅ **New** | Homepage via `config/seo.php` |
| HTTPS enforcement | ✅ **New** | `.htaccess` 301 + Laravel `URL::forceScheme` |
| Lazy loading | ✅ Implemented | Product cards, footer assets |
| WebP images | ⚠️ Partial | On upload; legacy images may remain JPG/PNG |
| Auto meta titles/descriptions | ✅ **Enhanced** | SA-focused product defaults |
| Image alt tags | ✅ **New** | `Product::imageAlt()` on cards & PDP |
| 301 redirect manager | ❌ Missing | Manual `.htaccess` / routes only |
| Pagination duplicate content | ✅ Improved | Canonical + prev/next |

---

## 2. Google indexing & Merchant compliance

| Feature | Status |
|--------|--------|
| Google Search Console verification | ✅ `GOOGLE_SITE_VERIFICATION` |
| Bing Webmaster verification | ✅ **New** `BING_SITE_VERIFICATION` |
| Sitemap ping (Google/Bing) | ✅ **New** Optional via `SEO_PING_SEARCH_ENGINES=true` |
| IndexNow API | ✅ **New** Optional via `INDEXNOW_KEY` |
| Google Merchant Center feed | ✅ `/feeds/google-merchant.xml` |
| Feed caching | ✅ **New** 30 min default |
| GTIN / MPN | ✅ Barcode field + admin fixes |
| Product availability in feed | ✅ in_stock / out_of_stock |
| PriceCheck feed | ✅ `/feeds/pricecheck.csv` |
| Rich snippets (reviews) | ❌ No on-site review system yet |

**Google Merchant actions:** Submit feed URL in Merchant Center → Products → Feeds. Fix any remaining GTIN issues via Admin → Catalog & Feeds.

---

## 3. Performance audit

| Area | Status | Target |
|------|--------|--------|
| Homepage cache | ✅ 10 min product blocks | |
| Sitemap/feed cache | ✅ **New** | |
| Static asset expires | ✅ `.htaccess` | |
| CSS preload | ✅ **New** | |
| Deferred JS | ✅ Bootstrap + search.js | |
| CSS/JS minification | ❌ Static files unminified | PageSpeed 90+ needs CDN + minify |
| Redis cache | ❌ Default: database cache | Set `CACHE_STORE=redis` on server |
| CDN for assets | ❌ | Cloudflare in front recommended |
| Responsive images (srcset) | ❌ | Future improvement |

**Estimated PageSpeed:** 65–80 mobile without CDN/minify; 85–92 achievable with Cloudflare + asset pipeline.

---

## 4. Ecommerce SEO features

| Feature | Status |
|--------|--------|
| SEO product pages | ✅ Unique title, description, keywords, schema |
| Category landing pages | ✅ Meta + CollectionPage schema |
| Related products | ✅ Same category |
| Recently viewed | ✅ **New** Session-based |
| Advanced filters | ✅ Brand, price, category, sort |
| Product comparison | ❌ Not built |
| Wishlist | ❌ Not built |
| Product FAQs | ❌ Not built (site FAQ schema on home) |
| Review system | ❌ Static testimonials only |
| Stock status | ✅ Cards + PDP |
| Delivery estimate | ✅ PDP meta cards |

---

## 5. South African keyword optimization

**Homepage & defaults now target:**
- buy laptops South Africa
- networking equipment South Africa
- Ubiquiti / Hikvision supplier South Africa
- business IT supplier
- computer accessories South Africa

**Geo signals:** `en_ZA`, Centurion address, nationwide delivery copy, city list in LocalBusiness schema (Johannesburg, Cape Town, Durban, Pretoria, Limpopo).

**Recommended content (blog):** Publish original guides — "Best laptops in South Africa", "Ubiquiti buying guide", "CCTV setup guide" — using Admin → Articles.

---

## 6. Trust & conversion

| Feature | Status |
|--------|--------|
| POPIA, privacy, terms, returns, warranty | ✅ |
| About & contact | ✅ |
| WhatsApp button | ✅ **New** |
| Newsletter signup | ✅ **New** Footer form |
| Trust bar / badges | ✅ Homepage |
| PayFast secure checkout | ✅ |
| VAT / company reg in footer | ✅ When env vars set |
| Customer reviews | ⚠️ Google Reviews config only |

---

## 7. Marketing & analytics

| Feature | Status |
|--------|--------|
| Google Analytics 4 | ✅ **New** `GA4_MEASUREMENT_ID` |
| Meta Pixel | ✅ **New** `META_PIXEL_ID` |
| TikTok Pixel | ✅ **New** `TIKTOK_PIXEL_ID` |
| Abandoned cart email | ❌ |
| Mailchimp / email automation | ❌ Newsletter emails admin only |
| Social auto-posting | ✅ Admin social queue |

---

## 8. Competitor comparison (high level)

| Capability | Urban Focus | Typical SA competitor |
|------------|-------------|----------------------|
| Google Merchant feed | ✅ Built-in | ✅ |
| PriceCheck feed | ✅ | ⚠️ Varies |
| B2B quotes / RFQ | ✅ Strong | ⚠️ Varies |
| Blog / guides | ✅ + RSS sync | ✅ |
| On-site reviews | ❌ | ✅ Often |
| Wishlist / compare | ❌ | ✅ Often |
| Brand depth | ✅ Configurable | ✅ |
| Trust/compliance pages | ✅ Strong | ⚠️ Varies |

**Urban Focus differentiators:** B2B procurement flow, Merchant GTIN tooling, multi-feed exports, POPIA compliance, authorised brand positioning.

---

## 9. Setup checklist (post-deploy)

1. Set in `.env`:
   - `GOOGLE_SITE_VERIFICATION`
   - `BING_SITE_VERIFICATION`
   - `GA4_MEASUREMENT_ID`
   - `META_PIXEL_ID` (optional)
   - `BUSINESS_VAT_NUMBER`, `BUSINESS_COMPANY_REG`
2. Submit sitemaps in Google Search Console & Bing Webmaster:
   - `https://www.urbanfocus.co.za/sitemap.xml`
   - `https://www.urbanfocus.co.za/sitemap-images.xml`
3. Submit Merchant feed in Google Merchant Center
4. Run `clear-cache.php` after deploy
5. Enable Cloudflare (recommended) for SSL, CDN, bot protection
6. Test PageSpeed: https://pagespeed.web.dev/

---

## 10. Recommended next actions (priority)

1. **High:** Add GA4 + Search Console; monitor indexing for product/category pages  
2. **High:** Enable Cloudflare; cache static assets  
3. **High:** Fix product brand/category data so Top Sellers shows major brands  
4. **Medium:** Build product review system + AggregateRating schema  
5. **Medium:** Wishlist + product comparison  
6. **Medium:** Abandoned cart email (requires queue + mail templates)  
7. **Medium:** Original SA buying-guide blog content (4–6 pillar articles)  
8. **Low:** Redis cache, asset minification pipeline, responsive srcset  
9. **Low:** AI search / recommendations (extend existing SearchService)

---

## Files changed in this optimization pass

- `config/seo.php` — central SEO & marketing config
- `app/Services/SeoService.php` — image sitemap, FAQ/breadcrumb helpers, cache ping
- `app/Services/FeedService.php` — feed caching
- `app/Models/Product.php` — SA meta defaults, image alt
- `resources/views/layouts/app.blade.php` — verification, analytics hooks
- `resources/views/partials/analytics.blade.php` — GA4, Meta, TikTok
- `resources/views/partials/whatsapp-button.blade.php`
- `resources/views/partials/pagination-seo.blade.php`
- `resources/views/partials/footer.blade.php` — newsletter, VAT/reg
- `public/.htaccess` — HTTPS redirect
- `.env.example` — new variables

---

*This report reflects codebase capabilities. Live scores depend on hosting, catalog size, image optimization, and ongoing content marketing.*

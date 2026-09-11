# Google Merchant Center product feed

Urban Focus exposes a Google Merchant Center RSS 2.0 feed of **customer-visible** catalogue products.

## Feed URL

`https://www.urbanfocus.co.za/feeds/google-merchant.xml`

Alias (same payload):

`https://www.urbanfocus.co.za/feeds/google.xml`

## Connect the feed

1. Open [Google Merchant Center](https://merchants.google.com/).
2. Go to **Products → Feeds**.
3. Add a feed for **South Africa** with currency **ZAR**.
4. Choose **Scheduled fetch**.
5. Enter the feed URL above (`/feeds/google-merchant.xml`).
6. Fetch daily. The application caches generated XML (see `SEO_FEED_CACHE_TTL`, default 6 hours).

## What is included

A product is submitted only when it is active and has:

- a public product URL
- a customer-facing description (internal pricing notes are stripped)
- a primary image
- a valid public price (quotation-only / zero-price items are **excluded**, not given a fake price)
- a brand
- a GTIN **or** SKU identifier

Availability, GTIN, MPN, additional images and Google product category are included only when real data exists.

## What is never included

- Supplier cost, margin, or internal catalogue notes
- Admin, account, or API data
- Inactive, duplicate, or quote-only listings without a public price
- Invented GTINs, reviews, or stock claims

After catalogue copy is cleaned in production (`php artisan catalog:scrub-internal-copy` or the cPanel scrub script), regenerate the feed cache (or wait for TTL expiry) so Merchant Center receives the sanitised descriptions.

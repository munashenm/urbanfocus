# Customer chat

This document describes the customer chat in this repository. It is not a statement that the feature is live on https://www.urbanfocus.co.za.

## What is implemented

- A chat panel on the storefront, with quick links to product search, quotes, order tracking and contact details.
- Availability based on the published hours (Monday to Friday, 08:00–17:00, Africa/Johannesburg). Outside those hours the panel says the team is offline and still offers the phone number and email address.
- Staff read and reply under Admin → Customer chat. Support, sales and admin roles that can view orders, customers or RFQs can open the inbox. A reply needs permission to edit orders, customers or RFQs.
- Product answers use the current catalogue (name, SKU, price, availability, warranty and published specifications). The OpenAI call, when it is turned on, runs on the server. The browser never receives the API key.
- If OpenAI is disabled, the key is missing, the request fails, or the model adds a price, discount, stock claim or date that is not in the catalogue, the customer sees the catalogue text instead.
- Returns, warranty and support forms store a request for staff with status `submitted`. The chat does not approve a return, refund or warranty claim.
- Order tracking in the chat needs the order number and the checkout email. A wrong pair gets the same message as an unknown order. The lookup result is not sent to OpenAI.

## What was tested here

Automated tests cover the widget, staff replies, offline copy, tracking privacy, warranty requests and the catalogue fallback. A browser check in this environment covers the panel on a desktop width and a narrow width. That check uses the local app, not urbanfocus.co.za.

This environment has no server `.env` and no `OPENAI_API_KEY`. The OpenAI path was tested with a mocked HTTP response. Catalogue fallback was tested with OpenAI left disabled.

## What is not done

The feature is not deployed. Nothing in this work was verified on https://www.urbanfocus.co.za. GitHub does not update the server by itself. cPanel has to deploy the checkout, which is when `.cpanel.yml` migrates and copies the public assets.

## Turn the assistant on

Customer chat uses the existing `OPENAI_API_KEY`, `OPENAI_BASE_URL` and `OPENAI_MODEL`. Do not add a second secret and do not commit a real key. `OPENAI_ENABLED` controls blog captions only. The product assistant stays off until `CHAT_OPENAI_ENABLED=true`, and only after that key is confirmed on the server.

```
CHAT_ENABLED=true
CHAT_OPENAI_ENABLED=false
OPENAI_API_KEY=the existing server key
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-4o-mini
```

With `CHAT_OPENAI_ENABLED=false`, or an empty key, product answers stay on the catalogue text. The rest of the chat still works.

Published hours shown to customers come from `BUSINESS_HOURS`. The open/closed flag uses Monday–Friday 08:00–17:00 in `config/chat.php`. If the published hours change, update both.

## Deploy on the server

`.cpanel.yml` clears caches, runs `php artisan migrate --force`, rebuilds the config and view caches, and copies `public/css`, `public/js` (including `chat.js`) and `public/images` into `public_html`. That runs when cPanel deploys the git checkout. It does not run from GitHub alone.

Leave `CHAT_OPENAI_ENABLED` unset or `false` until the existing server key is confirmed. Do not print that key.

If the hook does not run, after the code is on the server:

```
cd /home/urbanzdc/urbanfocus
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
/opt/cpanel/ea-php83/root/usr/bin/php -r 'require "deploy/cpanel-asset-sync.php"; cpanel_sync_public_assets("/home/urbanzdc/urbanfocus", "/home/urbanzdc/public_html");'
```

The checked-in `deploy/migrate.php` helper can run pending migrations if there is no shell. Set its secret, upload it, visit it once, then delete it. Do not leave it in `public_html`.

Confirm `public_html/js/chat.js` and `public_html/css/app.css` updated. On https://www.urbanfocus.co.za, open the chat, send a message, reply from Admin → Customer chat, and try tracking with a real order number plus the wrong email. Only after those checks should the feature be treated as live.

No new queue worker is required. Staff email uses the existing mailer during the request. If mail fails, the message still stays in the inbox.

## Rollback

Set `CHAT_ENABLED=false`, then `config:cache`. The tables can stay. Dropping them is only needed if the code that references them is removed.

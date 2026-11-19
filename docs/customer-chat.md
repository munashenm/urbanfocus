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

The feature is not deployed. Nothing in this work was verified on https://www.urbanfocus.co.za. cPanel's git deploy hook does not run migrations.

## Turn the assistant on

Customer chat reads the same variables as blog automation. Do not add a second secret and do not commit a real key.

```
OPENAI_ENABLED=true
OPENAI_API_KEY=the existing server key
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-4o-mini
CHAT_ENABLED=true
```

`OPENAI_ENABLED=false`, or an empty key, keeps product answers on the catalogue text. The rest of the chat still works.

Published hours shown to customers come from `BUSINESS_HOURS`. The open/closed flag uses Monday–Friday 08:00–17:00 in `config/chat.php`. If the published hours change, update both.

## Deploy on the server

After the code is on the server, and only then:

1. Confirm `OPENAI_API_KEY` is already in the server `.env` if the model should be used. Leave `OPENAI_ENABLED=false` until you want that.
2. Run the migration so `chat_conversations` and `chat_messages` exist:

```
cd /home/urbanzdc/urbanfocus
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
```

The checked-in `deploy/migrate.php` helper can run pending migrations if there is no shell. Set its secret, upload it, visit it once, then delete it. Do not leave it in `public_html`.

3. Rebuild the config cache so the chat settings are loaded:

```
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

4. Confirm `public_html/js/chat.js` and `public_html/css/app.css` updated. A normal page view copies `js/chat.js` when `PUBLIC_PATH` points at `public_html`. If the file is missing, open any storefront page once or run the existing public asset sync.
5. On https://www.urbanfocus.co.za, open the chat, send a message, reply from Admin → Customer chat, and try tracking with a real order number plus the wrong email. Only after those checks should the feature be treated as live.

No new queue worker is required. Staff email uses the existing mailer during the request. If mail fails, the message still stays in the inbox.

## Rollback

Set `CHAT_ENABLED=false`, then `config:cache`. The tables can stay. Dropping them is only needed if the code that references them is removed.

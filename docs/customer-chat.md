# Customer chat

This document describes the customer chat in this repository. It is not a statement that the feature is live on https://www.urbanfocus.co.za.

## What is implemented

- A chat panel on the storefront. One row of actions: search, quote, track an order, and talk to the team. Returns and warranty stay as text links.
- Availability based on the published hours (Monday to Friday, 08:00–17:00, Africa/Johannesburg). The header shows a short line (`Open until 17:00` or `Back Monday 08:00`). Outside those hours a message asks for a name and email, then confirms when the team will be back. Phone and email stay on the panel.
- Staff read and reply under Admin → Customer chat. Support, sales and admin roles that can view orders, customers or RFQs can open the inbox. A reply needs permission to edit orders, customers or RFQs.
- Product answers use the current catalogue (name, SKU, price, availability, warranty and published specifications). A question names a brand, a kind of product (laptop and notebook are the same, PC and desktop are the same) and a spec such as 16GB RAM. Cards are the products that match that combination, not every item that shares a number. The reply describes those cards. Service, policy and greeting questions do not get cards.
- Model replies are plain sentences. A JSON object or a code fence around JSON is unwrapped. Raw JSON is never shown.
- Questions about the company, services, quotes, delivery, payment and policies are answered from `config/chat_knowledge.php`, plus the live phone, email, hours and address in `config/business.php`, shipping rates in `config/shipping.php`, and the published FAQ in `config/seo.php`. The model may rephrase those lines. If it adds a price, a date, a stock claim or anything outside that text, the customer sees the knowledge text instead.
- Anything outside Urban Focus (general knowledge, coding, other companies, news, or an instruction to ignore the rules) is refused and pointed back to Urban Focus or Talk to the team. The refusal is decided in the app, before a model call.
- The OpenAI call, when it is turned on, runs on the server. The browser never receives the API key.
- If OpenAI is disabled, the key is missing, the request fails, or the model adds a price, discount, stock claim or date that is not in the catalogue, a product question shows the catalogue text instead.
- Returns, warranty and support forms store a request for staff with status `submitted`. The chat does not approve a return, refund or warranty claim.
- Order tracking in the chat needs the order number and the checkout email. A wrong pair gets the same message as an unknown order. The lookup result is not sent to OpenAI.

## What was tested here

Automated tests cover the widget, staff replies, offline copy, tracking privacy, warranty requests and the catalogue fallback. A browser check in this environment covers the panel on a desktop width and a narrow width. That check uses the local app, not urbanfocus.co.za.

This environment has no server `.env` and no `OPENAI_API_KEY`. The OpenAI path was tested with a mocked HTTP response. Catalogue fallback was tested with OpenAI left disabled.

## What is not done

The first version of the panel is on https://www.urbanfocus.co.za, and sending a message there returned HTTP 500. That save failure is fixed in this tree by storing the conversation token as `CHAR(36)` instead of MariaDB's native UUID type, and by a follow-up migration that converts an existing UUID column. It is not fixed on the website until cPanel deploys this checkout and `php artisan migrate --force` runs. Do not treat chat as working on the live site until a message sent on urbanfocus.co.za appears in Admin → Customer chat.

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

The assistant's business facts live in `config/chat_knowledge.php`. Edit that file to change what the bot may say about the company, services, quotes, delivery wording, payment, brands and the other topics listed there. Phone, email, hours and the street address stay in `config/business.php`. Courier fee and the free-shipping threshold stay in `config/shipping.php`. FAQ answers stay in `config/seo.php`. After the change is on `main`, cPanel needs **Update from Remote** and then **Deploy HEAD Commit** so the cached config is rebuilt. Do not edit these files on the server.

## Deploy on the server

GitHub does not update the live site by itself. The app code is the git checkout at `~/urbanfocus`. `public_html/index.php` only boots that checkout.

In cPanel → Git Version Control → the `urbanfocus` repository:

1. Click **Update from Remote** and wait until it finishes. That updates the checkout. It does not run migrations.
2. Click **Deploy HEAD Commit** and wait until it finishes. That runs `.cpanel.yml`.

`.cpanel.yml` calls `deploy/cpanel-deploy.sh`. The script picks a PHP 8.2+ binary (the domain handler in `public_html/.htaccess` when it names `ea-php` or `alt-php`, otherwise `/usr/local/bin/php`, `/usr/bin/php`, or EasyApache / CloudLinux PHP 8.2–8.4). It then clears cached config, runs `php artisan migrate --force` (pending migrations only, including the chat tables), rebuilds the config, route, and view caches, and copies `public/css`, `public/js` (including `chat.js`), `public/images`, favicons, and `.htaccess` into `public_html`.

It does not copy `public_html/index.php`, run Composer, or drop data. A cPanel PHP handler already present in `public_html/.htaccess` is kept when that file is updated.

The deployment log in cPanel should include `Migrations finished.` and `Deploy finished successfully.` The same text is appended to `urbanfocus/storage/logs/cpanel-deploy.log` (File Manager). If the log says migrations finished but a later step failed, the tables are already there; Deploy HEAD Commit again after the cause is fixed.

Leave `CHAT_OPENAI_ENABLED` unset or `false` until the existing server key is confirmed. Do not print that key.

Confirm `public_html/js/chat.js` and `public_html/css/app.css` updated. On https://www.urbanfocus.co.za, open the chat, send a message, and open Admin → Customer chat. The message should appear without an HTTP 500. Only after that should the feature be treated as live.

No new queue worker is required. Staff email uses the existing mailer during the request. If mail fails, the message still stays in the inbox.

## Rollback

Set `CHAT_ENABLED=false`, then `config:cache`. The tables can stay. Dropping them is only needed if the code that references them is removed.

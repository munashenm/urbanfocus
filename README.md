# Urban Focus Ecommerce Platform

Modern, SEO-friendly Laravel ecommerce platform for [Urban Focus](https://www.urbanfocus.co.za) — South African supplier of IT products and software.

Built for **cPanel shared hosting** (PHP 8.2+, MySQL/MariaDB).

## Features

- Product categories, search, filters, and SEO-friendly URLs (`/product/{slug}`)
- Shopping cart, checkout, customer accounts
- Admin dashboard: products, categories, orders, WooCommerce CSV import
- Paystack payment gateway + manual EFT option
- Shipping: flat rate courier, free shipping threshold, manual quote, collection
- Order confirmation emails
- SEO: meta fields, Product schema JSON-LD, XML sitemap, robots.txt
- Google Merchant Center feed (`/feeds/google-merchant.xml`)
- PriceCheck CSV export (`/feeds/pricecheck.csv`)
- Bob Shop trade feed XML (`/feeds/bobshop.xml`)
- Mobile-first responsive design
- CSRF protection, password hashing, input validation

## Tech Stack

- Laravel 11
- MySQL / MariaDB
- Blade templates + Bootstrap 5
- Session-based cart
- Database caching & sessions

## Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB in .env
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

**Default admin:** `admin@urbanfocus.co.za` / `ChangeMe123!` — change immediately after first login.

## cPanel Deployment Guide

### 1. Server Requirements

- PHP 8.2 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd` or `imagick`
- Composer (via SSH or upload `vendor` folder locally)

### 2. Upload Files

Upload the entire project to your cPanel account, e.g.:

```
/home/username/urbanfocus/
```

Do **not** point the domain document root at the project root. Only `public/` should be web-accessible.

### 3. Configure Document Root

In cPanel → **Domains** → your domain → set **Document Root** to:

```
/home/username/urbanfocus/public
```

Alternatively, if you cannot change document root, move contents of `public/` to `public_html/` and edit `public_html/index.php`:

```php
require __DIR__.'/../urbanfocus/vendor/autoload.php';
$app = require_once __DIR__.'/../urbanfocus/bootstrap/app.php';
```

Adjust paths to match your folder structure.

### 4. Create MySQL Database

1. cPanel → **MySQL Databases**
2. Create database: `username_urbanfocus`
3. Create user with strong password
4. Add user to database with **ALL PRIVILEGES**

### 5. Configure `.env`

Copy `.env.example` to `.env` on the server and set:

```env
APP_NAME="Urban Focus"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.urbanfocus.co.za

DB_DATABASE=username_urbanfocus
DB_USERNAME=username_ufuser
DB_PASSWORD=your_secure_password

MAIL_HOST=mail.urbanfocus.co.za
MAIL_USERNAME=sales@urbanfocus.co.za
MAIL_PASSWORD=your_email_password
MAIL_FROM_ADDRESS=sales@urbanfocus.co.za

PAYSTACK_PUBLIC_KEY=your-paystack-public-key
PAYSTACK_SECRET_KEY=your-paystack-secret-key
PAYSTACK_CURRENCY=ZAR

GOOGLE_SITE_VERIFICATION=your_verification_code
```

Generate app key via SSH:

```bash
cd ~/urbanfocus
php artisan key:generate
```

### 6. Install Dependencies & Migrate

Via SSH (Terminal in cPanel):

```bash
cd ~/urbanfocus
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If SSH is unavailable, run `composer install` locally and upload the `vendor/` folder.

### 7. File Permissions

```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Ensure `storage/` and `bootstrap/cache/` are writable by the web server.

### 8. Cron Jobs (Optional but Recommended)

cPanel → **Cron Jobs** — add:

```
* * * * * /usr/local/bin/php /home/username/urbanfocus/artisan schedule:run >> /dev/null 2>&1
```

Used for queued emails and scheduled tasks.

### 9. Email (SMTP)

Configure cPanel email account `sales@urbanfocus.co.za` and use those SMTP credentials in `.env`. Test with a contact form submission or test order.

### 10. Paystack Setup

1. Register at [Paystack](https://paystack.com)
2. In **Settings → API Keys & Webhooks**, copy your live Public and Secret keys into `.env`
3. Set the Webhook URL to: `https://www.urbanfocus.co.za/checkout/paystack/webhook`
4. Use `sk_test_` / `pk_test_` keys for testing, then switch to `sk_live_` / `pk_live_` for production

### 11. Google Search Console & Merchant Center

| Resource | URL |
|----------|-----|
| Sitemap | `https://www.urbanfocus.co.za/sitemap.xml` |
| Robots | `https://www.urbanfocus.co.za/robots.txt` |
| Merchant feed | `https://www.urbanfocus.co.za/feeds/google-merchant.xml` |
| PriceCheck feed | `https://www.urbanfocus.co.za/feeds/pricecheck.csv` |

1. Verify domain in Google Search Console (add `GOOGLE_SITE_VERIFICATION` to `.env`)
2. Submit sitemap URL
3. In Merchant Center, add product feed using the Google Merchant XML URL

### 12. Import WooCommerce Products

1. In WooCommerce: **Products → Export**
2. Admin → **Import CSV** → upload file
3. Products are matched by WooCommerce ID and updated on re-import

### 13. Post-Launch Checklist

- [ ] Change default admin password
- [ ] Set `APP_DEBUG=false`
- [ ] Enable HTTPS (AutoSSL in cPanel)
- [ ] Upload product images via admin
- [ ] Test Paystack with test keys then live payments
- [ ] Test order confirmation emails
- [ ] Submit sitemap to Google Search Console
- [ ] Configure Google Merchant Center feed

## Contact

- **Phone:** 087 550 1813
- **Email:** sales@urbanfocus.co.za
- **Website:** www.urbanfocus.co.za

## License

Proprietary — Urban Focus

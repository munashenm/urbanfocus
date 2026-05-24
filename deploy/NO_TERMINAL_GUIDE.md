# cPanel deployment WITHOUT Terminal

Use this guide if your cPanel has **no Terminal / SSH**.

Tools you need:
- **File Manager**
- **MySQL Databases**
- A web browser

Optional:
- **Composer** in cPanel (search "Composer" in cPanel)
- Or upload the `vendor` folder from your PC

---

## Before you start

Know your cPanel username. It is usually the prefix on database names, e.g. `urbanzdc_urbanfocusstore` → username is **`urbanzdc`**.

Your home folder on the server is:

```
/home/urbanzdc/
```

---

## STEP 1 — Open File Manager

1. Log into **cPanel**
2. Search or click **File Manager**
3. When asked "which directory", choose **Home Directory** (not only public_html)
4. Click **Go**

You should see folders including **`public_html`**.

---

## STEP 2 — Create the `urbanfocus` folder

1. Make sure you are in **/home/yourusername/** (home root, not inside public_html)
2. Click **+ Folder** (top left)
3. Name it: **`urbanfocus`**
4. Click **Create New Folder**

---

## STEP 3 — Move Laravel files into `urbanfocus`

1. Open **`public_html`**
2. Select these items (Ctrl+click / checkboxes):

   - `app`
   - `artisan`
   - `bootstrap`
   - `config`
   - `database`
   - `resources`
   - `routes`
   - `storage`
   - `composer.json`
   - `.env` (if it exists)
   - `.env.example`
   - `vendor` (if it exists)
   - `deploy`
   - `README.md`

3. Click **Move** (top toolbar)
4. Move to: **`/home/yourusername/urbanfocus`**
5. Click **Move Files**

Do **NOT** move `public_html/public` yet. Do **NOT** move `cgi-bin` if present.

---

## STEP 4 — Move website files to `public_html` root

1. Still in File Manager, open **`public_html/public`**
2. Select **all files inside** (`index.php`, `.htaccess`, `css` folder)
3. Click **Move**
4. Move to: **`/home/yourusername/public_html`**
5. Click **Move Files**
6. Go back to **`public_html/public`** — if empty, select the **`public`** folder → **Delete**

After this, **`public_html`** should contain roughly:
- `index.php`
- `.htaccess`
- `css/`
- maybe `cgi-bin/` (leave it)

**`urbanfocus`** should contain `app`, `config`, `.env`, etc.

---

## STEP 5 — Edit `public_html/index.php`

1. Open **`public_html`**
2. Right-click **`index.php`** → **Edit** → **Edit**
3. Delete all content
4. Paste this exactly:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravelRoot = dirname(__DIR__).'/urbanfocus';

if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

5. Click **Save Changes**

---

## STEP 6 — Configure `.env`

1. Go to **`urbanfocus`** folder (home directory, not public_html)
2. If **`.env`** does not exist:
   - Copy **`.env.example`**
   - Rename the copy to **`.env`**
3. Edit **`.env`** and set:

```env
APP_NAME="Urban Focus"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.urbanfocus.co.za

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=urbanzdc_urbanfocusstore
DB_USERNAME=urbanzdc_munashe
DB_PASSWORD=your_real_database_password

PUBLIC_PATH=/home/urbanzdc/public_html

PAYFAST_MERCHANT_ID=your_merchant_id
PAYFAST_MERCHANT_KEY=your_merchant_key
PAYFAST_PASSPHRASE=your_passphrase
PAYFAST_SANDBOX=true
```

Replace:
- `urbanzdc` with your cPanel username
- Database password and PayFast values with your real credentials

Leave **`APP_KEY=`** empty — setup script will generate it.

4. **Save**

---

## STEP 7 — MySQL database (if not done)

1. cPanel → **MySQL Databases**
2. Create database (or confirm exists): `urbanzdc_urbanfocusstore`
3. Create user (or confirm exists): `urbanzdc_munashe`
4. **Add User To Database** → grant **ALL PRIVILEGES**

---

## STEP 8 — Install `vendor` (Composer dependencies)

Laravel needs a **`vendor`** folder inside **`urbanfocus`**.

### Option A — cPanel has "Composer"

1. cPanel → search **Composer**
2. Set project path: `/home/urbanzdc/urbanfocus`
3. Run **Install**

### Option B — Upload vendor from your PC

On a computer with PHP and Composer installed:

```bash
git clone https://github.com/munashenm/urbanfocus.git
cd urbanfocus
composer install --no-dev
```

Then:
1. Zip the **`vendor`** folder
2. File Manager → **`urbanfocus`** → **Upload** the zip
3. Right-click zip → **Extract**
4. Confirm **`urbanfocus/vendor/`** exists
5. Delete the zip file

### Option C — Ask your host

Contact support: *"Please run `composer install --no-dev` in `/home/urbanzdc/urbanfocus`"*

---

## STEP 9 — Fix folder permissions (File Manager)

1. Open **`urbanfocus/storage`**
2. Right-click **`storage`** → **Change Permissions**
3. Set to **775** (or tick Owner/Group Read+Write+Execute)
4. Check **Recurse into subdirectories**
5. Apply

Repeat for **`urbanfocus/bootstrap/cache`** → **775**

---

## STEP 10 — Run setup in your browser (no Terminal)

1. In File Manager, open **`urbanfocus/deploy`**
2. Copy **`setup.php`**
3. Paste/move **`setup.php`** into **`public_html`**
4. Edit **`public_html/setup.php`**
5. Change this line to a secret only you know:
   ```php
   const SETUP_KEY = 'my-secret-setup-key-9876';
   ```
6. Save

7. Open in browser:
   ```
   https://www.urbanfocus.co.za/setup.php?key=my-secret-setup-key-9876
   ```
   (Use the same secret you set in the file)

8. Wait for all green "Done" messages

9. **Immediately delete `public_html/setup.php`** (security)

---

## STEP 11 — Test the site

| URL | Expected |
|-----|----------|
| https://www.urbanfocus.co.za | Home page |
| https://www.urbanfocus.co.za/shop | Shop |
| https://www.urbanfocus.co.za/login | Login |

**Admin login:**
- Email: `admin@urbanfocus.co.za`
- Password: `ChangeMe123!`
- Admin: https://www.urbanfocus.co.za/admin

**Change the admin password right away.**

---

## STEP 12 — Enable HTTPS

1. cPanel → **SSL/TLS Status** or **AutoSSL**
2. Run AutoSSL for your domain
3. cPanel → **Domains** → enable **Force HTTPS** if available

---

## Troubleshooting (no Terminal)

### "Forbidden" on setup.php
- URL must include `?key=` matching the key in setup.php

### "vendor folder missing"
- Complete STEP 8 first

### 500 error on homepage
1. File Manager → **`urbanfocus/storage/logs/laravel.log`**
2. Open and read the last error lines
3. Paste the error here for help

### Database connection error
- Use `DB_HOST=localhost` in `.env`
- Confirm MySQL user has ALL PRIVILEGES on the database

### CSS not loading
- Confirm `PUBLIC_PATH=/home/urbanzdc/public_html` in `.env`
- Confirm `public_html/css/app.css` exists
- Re-run setup.php (temporarily) or ask host to run `php artisan config:cache`

### Can't see `.env` in File Manager
- Top right → **Settings** → enable **Show Hidden Files (dotfiles)**

---

## Checklist

- [ ] `urbanfocus/` has app, config, storage, vendor, .env
- [ ] `public_html/` has index.php, .htaccess, css/
- [ ] index.php updated (STEP 5)
- [ ] .env configured with PUBLIC_PATH (STEP 6)
- [ ] vendor folder installed (STEP 8)
- [ ] storage and bootstrap/cache permissions 775 (STEP 9)
- [ ] setup.php run in browser (STEP 10)
- [ ] setup.php deleted (STEP 10)
- [ ] HTTPS enabled (STEP 12)

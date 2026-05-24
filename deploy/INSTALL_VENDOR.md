# Install vendor without Composer on cPanel

Your host has no Composer. Use **one** of these options.

---

## Option 1 — Download from GitHub (recommended)

We added a GitHub Action that builds a zip **including `vendor/`**.

### Steps

1. Open: **https://github.com/munashenm/urbanfocus/actions**
2. Click the latest **"Build cPanel Deploy Package"** workflow run (green checkmark)
3. Scroll down to **Artifacts**
4. Download **`urbanfocus-cpanel-vendor`**
5. Unzip on your PC — you get the full project with `vendor/` inside

### Upload to cPanel

**If you already restructured (Plan B):**

1. Unzip on your PC
2. Upload only the **`vendor`** folder to **`/home/yourusername/urbanfocus/vendor`**
   - Zip `vendor` first (may be 20–40 MB)
   - File Manager → `urbanfocus` → Upload → Extract

**If you have NOT moved files yet:**

1. You can upload the whole zip to home directory and extract
2. Then follow the folder move steps in `deploy/NO_TERMINAL_GUIDE.md`

Confirm this path exists on server:

```
/home/yourusername/urbanfocus/vendor/autoload.php
```

---

## Option 2 — Ask your host (easiest if they agree)

Open a support ticket:

> Please run `composer install --no-dev --optimize-autoloader` in `/home/USERNAME/urbanfocus` for my Laravel site. PHP 8.2+ required.

Replace `USERNAME` with your cPanel username.

---

## Option 3 — Install Composer on your Windows PC

If you have **winget** (Windows 10/11):

```powershell
winget install PHP.PHP.8.2
```

Close and reopen PowerShell, then:

```powershell
cd "C:\Users\Nimrod\Documents\Gravity Projects\urbanfocus"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php composer.phar install --no-dev --optimize-autoloader
```

Zip the **`vendor`** folder and upload to cPanel → **`urbanfocus/vendor`**.

---

## After vendor is uploaded

Continue with:

1. `.env` configured (including `PUBLIC_PATH`)
2. Permissions 775 on `storage` and `bootstrap/cache`
3. Run **`setup.php`** in browser (see `deploy/NO_TERMINAL_GUIDE.md`)
4. Delete **`setup.php`**

---

## How to verify vendor is correct

In File Manager, this file must exist:

```
urbanfocus/vendor/autoload.php
```

If missing, the site will show a fatal error about autoload.php.

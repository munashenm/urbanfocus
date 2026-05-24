# cPanel Plan B — public_html as web root

Use this when cPanel **cannot** point the domain to a `public/` subfolder.

## Final folder layout

```
/home/USERNAME/
├── urbanfocus/          ← Laravel app (NOT web accessible)
│   ├── app/
│   ├── artisan
│   ├── bootstrap/
│   ├── config/
│   ├── .env
│   └── ...
└── public_html/         ← Web root (only public files)
    ├── index.php
    ├── .htaccess
    ├── css/
    └── storage/         ← symlink created by artisan storage:link
```

## One-time restructure (Terminal)

Replace `USERNAME` with your cPanel username (e.g. `urbanzdc`).

```bash
cd ~
mkdir -p urbanfocus

# Move Laravel out of public_html
mv public_html/app urbanfocus/
mv public_html/artisan urbanfocus/
mv public_html/bootstrap urbanfocus/
mv public_html/config urbanfocus/
mv public_html/database urbanfocus/
mv public_html/resources urbanfocus/
mv public_html/routes urbanfocus/
mv public_html/storage urbanfocus/
mv public_html/composer.json urbanfocus/
mv public_html/.env urbanfocus/ 2>/dev/null || true
mv public_html/vendor urbanfocus/ 2>/dev/null || true

# Move public assets into public_html root
mv public_html/public/index.php public_html/
mv public_html/public/.htaccess public_html/
mv public_html/public/css public_html/ 2>/dev/null || true
rm -rf public_html/public
```

Copy `deploy/public_html/index.php` from the repo to `public_html/index.php`, or edit `public_html/index.php` to match that file.

Add to `urbanfocus/.env`:

```env
PUBLIC_PATH=/home/USERNAME/public_html
DB_HOST=localhost
```

Then run all artisan commands from `~/urbanfocus`:

```bash
cd ~/urbanfocus
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
chmod -R 775 storage bootstrap/cache
```

# Update code on cPanel (urbanfocus + public_html layout)

Your live site uses:

```
/home/USERNAME/
├── urbanfocus/     ← Laravel app (Git should live HERE)
└── public_html/    ← Web root only (index.php, css, .htaccess)
```

Git was originally set to `public_html`. That is no longer correct.

---

## Option A — Fix Git to pull into `urbanfocus` (recommended)

### Step 1 — Show hidden files

File Manager → **Settings** → enable **Show Hidden Files**

### Step 2 — Remove old Git link from `public_html`

If `public_html/.git` exists:

1. File Manager → `public_html`
2. Delete the **`.git`** folder only
3. Do **NOT** delete `index.php`, `.htaccess`, or `css/`

Also remove `public_html/.gitignore` if present (optional).

### Step 3 — Set up Git on `urbanfocus`

1. cPanel → **Git Version Control**
2. Click **Create**
3. Fill in:
   - **Clone URL:** `https://github.com/munashenm/urbanfocus.git`
   - **Repository Path:** `urbanfocus`
   - **Name:** Urban Focus
4. Click **Create**

**If cPanel says the folder already exists and clone fails:**

1. Rename `urbanfocus` → `urbanfocus_backup` (File Manager)
2. Clone fresh into `urbanfocus`
3. Copy back from backup:
   - `.env`
   - `storage/` (logs, uploads)
   - `vendor/` (if you don't want to re-upload)
4. Delete `urbanfocus_backup` when happy

### Step 4 — Protect `.env`

`.env` is in `.gitignore` — pulls will **not** overwrite it. Keep your live `.env` in `urbanfocus/.env`.

### Step 5 — Pull updates

1. cPanel → **Git Version Control**
2. Click **Manage** next to your repository
3. Click **Pull** or **Update from Remote**
4. Confirm pull succeeded

### Step 6 — Clear caches (no Terminal)

1. Copy `deploy/clear-cache.php` → `public_html/clear-cache.php`
2. Edit the secret key in the file
3. Visit: `https://www.urbanfocus.co.za/clear-cache.php?key=YOUR_SECRET`
4. **Delete `clear-cache.php`** when done

---

## Option B — No Git changes: upload files manually

Use this if Git Version Control is unavailable or confusing.

### Step 1 — Download latest code

Download ZIP:  
https://github.com/munashenm/urbanfocus/archive/refs/heads/main.zip

Extract on your PC.

### Step 2 — Upload to `urbanfocus` only

Upload these into **`urbanfocus`** (overwrite when asked):

**New files (create folders if needed):**
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `resources/views/account/profile.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/users/form.blade.php`

**Updated files:**
- `routes/web.php`
- `resources/views/account/dashboard.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/partials/header.blade.php`

**Do NOT overwrite:**
- `urbanfocus/.env`
- `urbanfocus/storage/` (your logs and uploads)
- `urbanfocus/vendor/` (unless you know you need to update dependencies)

### Step 3 — Clear cache

Use `clear-cache.php` as in Option A Step 6,  
**OR** File Manager → delete all `.php` files in `urbanfocus/bootstrap/cache/` (keep `.gitignore`).

### Step 4 — Test

- https://www.urbanfocus.co.za/admin/users
- https://www.urbanfocus.co.za/account/profile

---

## What NOT to put in `public_html`

After Plan B setup, `public_html` should only contain:

- `index.php`
- `.htaccess`
- `css/`
- `storage/` (symlink or folder for images)
- Temporary scripts (`setup.php`, `clear-cache.php`) — delete after use

**Do not** pull the full GitHub repo into `public_html`.

---

## Future updates (simple workflow)

1. We push changes to GitHub
2. cPanel → Git Version Control → **Pull** on `urbanfocus` repo
3. Run `clear-cache.php` in browser (or delete bootstrap/cache/*.php)
4. Test the site

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Pull overwrites nothing | Git may still point at `public_html` — move Git to `urbanfocus` |
| 404 on `/admin/users` | Clear route cache with `clear-cache.php` |
| `.env` missing after clone | Copy from backup or `.env.example` |
| Pull blocked on `deploy/clear-cache.php` | Delete `urbanfocus/deploy/clear-cache.php`, pull again, copy to `public_html` and set key there only |
| `/blog/category/*` 500 | Run migrate (`clear-cache.php?migrate=1`). Category column must exist — migration `000091` |
| Admin 403 after login | Run migrations, then `seed-roles.php` (see `deploy/seed-roles.php`) |

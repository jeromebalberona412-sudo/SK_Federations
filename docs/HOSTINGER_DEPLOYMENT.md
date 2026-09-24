# SK Federations — Hostinger production deployment

Production domain (Hostinger `.env`): `https://federations.skoneportal.com`

(Older local templates may still show `skfederations.skoneportal.com` — production must use the live Hostinger hostname in `.env` only, not in source code.)

PHP requirement: **8.2 or newer** (Laravel 12).

Do **not** run `composer run setup` on Hostinger. That script generates a new `APP_KEY` and runs migrations automatically.

Do **not** set `APP_DEBUG=true` on production.

Do **not** rotate `APP_KEY` if one already exists. A new key invalidates sessions and encrypted data.

---

## Automatic Vite `public/build` (required)

`public/build/` stays **gitignored**. Do **not** upload it manually.

### Preferred: GitHub Actions (works even when Hostinger has no Node)

Workflow: `.github/workflows/deploy-sk-federations.yml`

On push to `main` under `SK_Federations/**` (or manual **workflow_dispatch**):

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci`
3. `npm run build` → writes `public/build/manifest.json`
4. Fails the job if the manifest is missing
5. rsync to Hostinger (**includes** `public/build`, **excludes** `.env`)
6. SSH: `php artisan optimize:clear` && `php artisan optimize`

**GitHub secrets to set** (Settings → Secrets and variables → Actions):

| Secret | Purpose |
|--------|---------|
| `SSH_PRIVATE_KEY` | Same as Admin deploy |
| `SSH_HOST` / `SSH_USERNAME` / `SSH_PORT` | Same as Admin deploy |
| `HOSTINGER_TARGET_FEDERATIONS` | Absolute app root on Hostinger (directory that contains `artisan` and `public/`) |

Example target (adjust to your Hostinger path):

```text
/home/u793353577/domains/YOUR-FED-HOST/public_html/
```

### Optional: Hostinger Git post-deploy (only if Node/npm exist on the server)

```bash
chmod +x deploy.sh && ./deploy.sh
```

If Node is missing on Hostinger, use GitHub Actions above — do not upload `public/build` by hand.

---

## Automatic deploy after `git push` (required Hostinger setting)


`deploy.sh` in this folder is **not** executed by Git alone. Hostinger must be told to run it after each pull.

### If this website uses Hostinger Git (hPanel → Websites → [site] → Git)

1. Confirm the Git repository / branch for this site.
2. Open the Git deployment settings for the site.
3. Find the field for **commands that run after deployment / after pull** (wording varies by Hostinger UI version: often “Deploy commands”, “Build command”, or “Hooks”).
4. Paste **one** of these, depending on how the repo is checked out on the server:

**App directory is the website root** (files like `artisan`, `composer.json`, `deploy.sh` are in the document-root parent):

```bash
chmod +x deploy.sh && ./deploy.sh
```

**Monorepo checkout** (repo root contains `SK_Federations/`):

```bash
chmod +x SK_Federations/deploy.sh && cd SK_Federations && ./deploy.sh
```

5. Save, then trigger a deploy (or push to the connected branch).
6. Confirm the deploy log shows all five steps completing.

### If this site is updated by SSH / `git pull` instead of Hostinger Git

After pull, run from the app root:

```bash
chmod +x deploy.sh && ./deploy.sh
```

### What Hostinger must already have

- PHP 8.2+ with Composer on PATH
- Node.js + npm on PATH (Vite build). If Node is unavailable on the plan, `deploy.sh` will fail at npm — install Node for the site/SSH user or build assets elsewhere and sync `public/build` before relying on auto-deploy
- Production `.env` already present on the server (never from Git)

Existing repo note: only `Admin/` has a GitHub Actions → SSH rsync workflow (`.github/workflows/deploy-admin.yml`). That does **not** deploy SK Federations.

---

## 1. Hostinger settings (must be correct)

1. Point the domain document root to the Laravel **`public`** directory:
   - `.../SK_Federations/public`
2. If Hostinger only allows the project root as the document root, keep the existing project-root `.htaccess` (it rewrites into `public/` and blocks `.env`).
3. PHP version: 8.2+
4. Enable PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `json`, `ctype`, `fileinfo`, `xml`, `curl`, `zip`, `gd` or `imagick`, `bcmath`
5. Force HTTPS on the domain (Hostinger SSL + Cloudflare)

---

## 2. Upload / pull

Upload or git-pull the `SK_Federations` project. Do not upload `.env` from a local machine.

Preferred: connect Hostinger Git and set the post-deploy command to `./deploy.sh` (see above).

---

## 3. What `deploy.sh` runs (no migrations)

```bash
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm ci --no-fund --no-audit
npm run build
php artisan optimize:clear
php artisan optimize
```

Never use `composer update` or `npm install` for routine production deploys when lockfiles exist.

---

## 4. Configure `.env` (once on the server)

Set at least (values stay on Hostinger only):

```env
APP_NAME="SK Federations"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://federations.skoneportal.com
SK_FED_APP_URL=https://federations.skoneportal.com
SK_OFFICIALS_APP_URL=https://skofficials.skoneportal.com
KABATAAN_APP_URL=https://kabataan.skoneportal.com

APP_KEY=base64:...existing production key...

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

`DB_HOST=localhost` is normal on Hostinger shared hosting. Do not copy local `127.0.0.1` SQLite settings.

Do not hard-code these URLs in application source; keep them in `.env`.

---

## 5. Storage

```bash
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

Create the public storage link only if it does not already exist:

```bash
php artisan storage:link
```

Do not use `chmod 777`.

---

## 6. Safe migrations (manual only)

```bash
php artisan migrate --force
```

Never run `migrate:fresh`, `migrate:reset`, `db:wipe`, or unsolicited `db:seed`.

---

## 7. Intended developer workflow

```text
git add .
git commit -m "..."
git push
    → Hostinger pulls
    → post-deploy runs ./deploy.sh
    → composer install / npm ci / npm run build / artisan optimize
    → site ready
```

Local development continues to use each developer’s own `.env` (for example `APP_URL=http://localhost:8000`).

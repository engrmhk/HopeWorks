# cPanel Go-Live — HopeWorks Control Plane + Church Apps

**Audience:** deploying the single Control Plane and connecting Synod / Independent Church apps (`HopeWorks-church`).

## Architecture (what goes where)

| Piece | Repo | How many | Role |
|-------|------|----------|------|
| Control Plane | `HopeWorks` | **One** | Admin at `/admin`, billing, licenses, directory of churches |
| Church app | `HopeWorks-church` | **One per Synod or Independent Church** | Day-to-day church CMS; pulls license via heartbeat |

```
Church apps ──POST /api/v1/heartbeat──► Control Plane
             ◄── license JWT + status ──
```

Churches **pull**; the Control Plane does **not** push into church instances.

---

## 0. Hosting prerequisites (cPanel)

On the Control Plane account (and each church account):

- **PHP 8.2+** (8.3/8.4 preferred). Enable extensions: `openssl`, `pdo_mysql` (or pgsql), `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `gd`/`imagick`
- **MySQL** (typical on cPanel) or PostgreSQL if available
- Ability to set **document root** to `public/`
- **Cron** (required)
- SSH + Composer preferred; if no SSH, use Terminal / Softaculous / Composer UI carefully
- HTTPS (Let's Encrypt)

Recommended domains:

- Control Plane: `https://control.yourdomain.com` (or `https://admin.yourdomain.com`)
- Each church: `https://synod-name.yourdomain.com` or a custom domain

---

## 1. Prepare a clean release package (local)

From your Mac, **do not upload** `vendor`, `node_modules`, `.env`, `composer.phar`, or local SQLite.

```bash
cd /path/to/HopeWorks
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# Upload code + public/build; OR run composer/npm on the server if PHP/Node allow it
```

On cPanel, safest pattern:

1. Upload source **without** `vendor/` and `.env` (respect `.gitignore`)
2. SSH / Terminal: `composer install --no-dev --optimize-autoloader`
3. Ensure `public/build` exists (build assets locally and upload `public/build`, or build on server)

---

## 2. Deploy Control Plane on cPanel

### 2.1 Files

1. Create subdomain / domain → point **document root** to `.../HopeWorks/public`
2. Place the app outside `public_html` if possible (e.g. `~/apps/hopeworks-control`) and only expose `public`
3. If document root cannot leave `public_html`, put the app in a folder and set the subdomain root to that folder’s `public`

### 2.2 `.env` (create on server — never commit)

```env
APP_NAME="Hope Works"
APP_ENV=production
APP_KEY=   # php artisan key:generate
APP_DEBUG=false
APP_URL=https://control.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_cp_db
DB_USERNAME=your_cp_user
DB_PASSWORD=your_cp_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public

# MUST match every church app LICENSE_JWT_SECRET exactly (32+ characters; not the Instance API key)
LICENSE_JWT_SECRET=generate-a-long-random-secret-at-least-32-chars
INTERNAL_SERVICE_SECRET=another-long-random-secret

AFFILIATION_CHANGE_ENABLED=false

# Stripe (when ready)
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=USD
BILLING_CURRENCY=USD
STRIPE_CHECKOUT_SUCCESS_URL="${APP_URL}/admin/subscriptions?checkout=success"
STRIPE_CHECKOUT_CANCEL_URL="${APP_URL}/admin/subscriptions?checkout=cancelled"
STRIPE_PORTAL_RETURN_URL="${APP_URL}/admin"

MESSAGING_DRIVER=mail
SUPPORT_STAFF_NOTIFY_TO=you@yourdomain.com
MAIL_MAILER=smtp
# … fill SMTP from cPanel email / provider
```

### 2.3 Install commands (SSH)

```bash
cd ~/apps/hopeworks-control   # or your path
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # creates admin@hopeworks.test — CHANGE PASSWORD immediately
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.4 Cron (required)

cPanel → Cron Jobs → every minute:

```bash
* * * * * cd /home/USER/apps/hopeworks-control && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary path shown in cPanel “Select PHP Version” / MultiPHP. This runs `subscriptions:evaluate-statuses` every minute.

### 2.5 Permissions

```bash
chmod -R ug+rwx storage bootstrap/cache
```

### 2.6 Smoke test Control Plane

1. Open `https://control.yourdomain.com/admin`
2. Log in, change admin password
3. Create a **Plan**, then a **Synod**, then a **Church**
4. Set Church **Status = Active**
5. Set **Instance URL** to the future church app URL (e.g. `https://1stchurch.yourdomain.com`)
6. Header → **API Key → Generate API Key** — copy the plain key once
7. Note: keep `LICENSE_JWT_SECRET` for the church `.env`

### 2.7 Stripe webhook (when billing goes live)

Stripe Dashboard → Webhook endpoint:

`https://control.yourdomain.com/api/v1/webhooks/stripe`

Events: checkout / invoice / subscription payment events your CP already handles. Paste signing secret into `STRIPE_WEBHOOK_SECRET`.

---

## 3. Deploy each Church app (HopeWorks-church)

Repeat per Synod or Independent Church.

### 3.1 Files + document root

Same pattern: app root + document root = `public/`.

### 3.2 Church `.env`

```env
APP_NAME="1st Church"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://1stchurch.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=church_1_db
DB_USERNAME=...
DB_PASSWORD=...

# Point at the live Control Plane
CONTROL_PLANE_URL=https://control.yourdomain.com
CONTROL_PLANE_API_KEY=paste-the-key-from-cp-generate-api-key

# MUST be identical to Control Plane LICENSE_JWT_SECRET
LICENSE_JWT_SECRET=same-secret-as-control-plane

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public
```

### 3.3 Install

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
# seed per your church onboarding process
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

### 3.4 Cron (required on every church)

```bash
* * * * * cd /home/USER/apps/hopeworks-church-1 && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

This runs `hopeworks:sync-license` hourly (among other schedules).

### 3.5 Pair / verify connection

On the church server:

```bash
php artisan hopeworks:sync-license
# or with church id if multi-tenant instance:
php artisan hopeworks:sync-license --church=1
```

Or in church admin → **System Diagnostics → Sync Now**.

**Success looks like:**

- Church diagnostics: status + `current_period_end` match Control Plane
- Control Plane church edit: **Last successful heartbeat** updates
- No 401/403 on heartbeat

**Common failures:**

| Symptom | Fix |
|---------|-----|
| 401 Unauthorized | Wrong `CONTROL_PLANE_API_KEY` or key revoked; regenerate on CP |
| 403 deactivated | Church **Status** not Active on CP |
| JWT / license invalid | `LICENSE_JWT_SECRET` mismatch between CP and church |
| HTTP 503 `license_jwt_secret_too_short` | Control Plane `LICENSE_JWT_SECRET` is empty or under 32 characters. Set the same long secret on CP `.env` and church System → License JWT secret, then `php artisan config:clear` on CP. |
| Connection refused / SSL | Wrong `CONTROL_PLANE_URL`; must be HTTPS public URL, no trailing slash issues |
| Sync works but “old expiry” | Ensure both sides have the `current_period_end` heartbeat fields deployed |

---

## 4. Connecting checklist (one church)

1. [ ] CP: Church created, Active, plan/subscription set, `instance_url` filled  
2. [ ] CP: Instance API key generated; plain key stored in church secrets manager / `.env`  
3. [ ] Church: `CONTROL_PLANE_URL` + `CONTROL_PLANE_API_KEY` + matching `LICENSE_JWT_SECRET`  
4. [ ] Both: cron `schedule:run` every minute  
5. [ ] Church: Sync Now / `hopeworks:sync-license` succeeds  
6. [ ] CP: heartbeat timestamp visible  
7. [ ] Change subscription period / status on CP → church Sync Now → diagnostics show billing `current_period_end`  
8. [ ] (Optional) Branding on each side independently  

---

## 5. Security go-live rules

- `APP_DEBUG=false` everywhere in production  
- Never commit `.env`, `composer.phar`, dumps, or uploaded media  
- Rotate admin password after seed  
- Prefer different DB users per app  
- Keep `AFFILIATION_CHANGE_ENABLED=false` until joint verification is signed off  
- Same `LICENSE_JWT_SECRET` on CP + all churches (treat as a shared signing secret; rotate carefully with downtime/re-sync)  

---

## 6. What not to upload

Ignored by `.gitignore` (and should stay off the server zip):

- `.env*` (except you create `.env` only on the server)
- `vendor/` (install on server with Composer)
- `node_modules/`
- `composer.phar`
- `*.zip`, `*.sql`, local `*.sqlite`
- IDE folders, `.cursor`, test caches
- `storage/logs`, compiled views, uploaded `storage/app/public` from local (unless intentional)

---

## 7. Minimal “first live church” path

1. Deploy Control Plane → migrate/seed → cron  
2. Create Plan + Church + Generate API Key  
3. Deploy one HopeWorks-church → migrate → same JWT secret + API key + CP URL → cron  
4. Sync license → confirm heartbeat  
5. Only then invite church staff  

Stripe / portal / affiliation can wait until the license heartbeat path is solid.

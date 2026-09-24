# cPanel Go-Live — HopeWorks Control Plane + Church Apps

**Audience:** deploying the single Control Plane and connecting Synod / Independent Church apps (`HopeWorks-church`).

## Architecture (what goes where)

| Piece | Repo | How many | Role |
|-------|------|----------|------|
| Control Plane | `HopeWorks` | **One** | Admin at `/admin`, billing, licenses, directory of churches |
| Church app (standalone) | `HopeWorks-church` | **One deploy + one DB** | Independent parish; **one** Control Plane church record |
| Church app (synod co-hosted) | `HopeWorks-church` | **One deploy + one shared DB** | Synod host + member churches; **one Control Plane church record and Instance API key per member church** |

```
Each church record ──POST /api/v1/heartbeat (its own API key)──► Control Plane
                     ◄── license JWT + status ──
```

Churches **pull**; the Control Plane does **not** push into church instances.

**Shared vs per-church**

| Value | Shared? | Where on Control Plane | Where on church app |
|-------|---------|------------------------|---------------------|
| Instance API key (`hw_…`) | No — one per church | Church connection | Settings → System (not `.env`) |
| Synod API key (`hw_…`) | No — one per synod (optional) | Synod → Synod connection | Synod host Settings → System |
| Control Plane church ID | No — one per church | Church connection | Settings → System |
| JWT secret | Yes — one platform secret | Settings → License connection | Settings → System (child churches may inherit from the synod host if left empty) |
| Control Plane URL | Yes | Church connection | Settings → System (children may inherit) |
| Synod disable / banner | Yes — one synod record | Synod → Access & banner | Delivered to **every** member church on next heartbeat |

The synod itself is a **free** account (no subscription). Member churches keep their own billing. Mark the synod Control Center church as **Synod host (free)** if it should heartbeat without a plan.

Do not put URL / API key / JWT in the church `.env` when using Settings → System.

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

# Optional. Leave empty and generate the JWT secret in admin after migrate
# (Settings → License connection). Env is only a fallback if admin has no secret yet.
LICENSE_JWT_SECRET=
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
6. Open the church → **Church connection** → **Generate API key** — copy the yellow `hw_…` key once
7. **Settings → License connection** (or the same Church connection box) → **Generate JWT secret** — copy that value into the church System page (not the Instance API key)

### 2.7 Stripe webhook (when billing goes live)

Stripe Dashboard → Webhook endpoint:

`https://control.yourdomain.com/api/v1/webhooks/stripe`

Events: checkout / invoice / subscription payment events your CP already handles. Paste signing secret into `STRIPE_WEBHOOK_SECRET`.

---

## 3. Deploy each Church app (HopeWorks-church)

**Standalone:** one deploy, one database, one Control Plane church.

**Synod co-hosted:** one deploy, **one shared database**, many church rows in that database. Create the **Synod** (free — no subscription) and use **Access & banner** plus **Synod connection**. Create **one church + Instance API key per congregation**. Member churches still need their own paid subscription. The synod Control Center church can be marked **Synod host (free)** and skip billing. Set every member’s `instance_url` to the **same** synod app URL.

### 3.1 Files + document root

Same pattern: app root + document root = `public/`.

### 3.2 Church `.env`

Leave Control Plane fields empty when using **Settings → System** (recommended). Use `KEY=value` only — never `KEY: value`.

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

# Leave empty when using Settings → System:
CONTROL_PLANE_URL=
CONTROL_PLANE_API_KEY=
LICENSE_JWT_SECRET=

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

This runs `hopeworks:sync-license` hourly (among other schedules). On a synod install, sync **each** member church (`hopeworks:sync-license --church={id}`).

### 3.5 Pair / verify connection

On Control Plane, open the church → **Church connection** and copy:

1. Control Plane URL  
2. Church ID (must match the church app’s Control Plane church ID)  
3. Instance API key (`hw_…`)  
4. JWT secret (same value for every church; generate once under **Settings → License connection**)

On the church app → **Settings → System** → paste those values → **Sync Now**. Child churches on a synod may leave URL/JWT empty to inherit from the synod host; **each child still needs its own Instance API key**.

On the church server you can also run:

```bash
php artisan hopeworks:sync-license
# synod / multi-church instance:
php artisan hopeworks:sync-license --church=1
```

**Success looks like:**

- Church diagnostics: status + `current_period_end` match Control Plane
- Control Plane church edit: **Last successful heartbeat** updates
- No 401/403 on heartbeat

**Common failures:**

| Symptom | Fix |
|---------|-----|
| 401 Unauthorized | Wrong `CONTROL_PLANE_API_KEY` or key revoked; regenerate on CP |
| 403 deactivated | Church **Status** not Active on CP |
| JWT / license invalid | JWT secret on church System page does not match Control Plane admin (Settings → License connection) |
| HTTP 503 `license_jwt_secret_too_short` | Generate or paste the JWT secret in Control Plane **Settings → License connection** (or Church connection). Copy the same value to church System → License JWT secret. |
| Connection refused / SSL | Wrong `CONTROL_PLANE_URL`; must be HTTPS public URL, no trailing slash issues |
| Sync works but “old expiry” | Ensure both sides have the `current_period_end` heartbeat fields deployed |

---

## 4. Connecting checklist (one church)

1. [ ] CP: Church created, Active, plan/subscription set  
2. [ ] CP: `instance_url` = this church’s app URL (synod members: **same URL as the synod host**)  
3. [ ] CP: Instance API key generated; paste into church **Settings → System** (not `.env`)  
4. [ ] Church: paste Control Plane URL, **Church ID**, Instance API key, and JWT secret (or inherit URL/JWT from synod host)  
5. [ ] Both: cron `schedule:run` every minute  
6. [ ] Church: Sync Now / `hopeworks:sync-license` succeeds  
7. [ ] CP: heartbeat timestamp visible  
8. [ ] Change subscription period / status on CP → church Sync Now → diagnostics show billing `current_period_end`  
9. [ ] (Optional) Branding on each side independently  

---

## 5. Security go-live rules

- `APP_DEBUG=false` everywhere in production  
- Never commit `.env`, `composer.phar`, dumps, or uploaded media  
- Rotate admin password after seed  
- Prefer different DB users per app  
- Keep `AFFILIATION_CHANGE_ENABLED=false` until joint verification is signed off  
- Same JWT secret on Control Plane admin and church System pages (synod children may inherit the host’s copy; rotate carefully)  

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
2. Create Plan + Church + Generate API key; Generate JWT secret in admin  
3. Deploy HopeWorks-church (standalone **or** synod co-host) → migrate → paste CP URL, **Church ID**, API key, and JWT on Settings → System → cron   
4. Sync license → confirm heartbeat  
5. Only then invite church staff  

Stripe / portal / affiliation can wait until the license heartbeat path is solid.

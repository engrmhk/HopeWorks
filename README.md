# HopeWorks Control Plane

Hope Works' internal admin application. There is only ever **one instance** of this app — it is never forked or given to a client. It manages the directory of all synods and churches, billing/subscriptions, licensing, and cross-client reporting.

**Related repo:** [HopeWorks-CoreClient](https://github.com/engrmhk/HopeWorks-CoreClient) — the forkable per-client application deployed once per Synod or Independent Church.

> **Split note:** Core Client code lives on the [`core-client`](https://github.com/engrmhk/HopeWorks/tree/core-client) branch until the separate `HopeWorks-CoreClient` repository is created. To finalize the split, create an empty `HopeWorks-CoreClient` repo on GitHub and run:
> ```bash
> git clone -b core-client https://github.com/engrmhk/HopeWorks.git HopeWorks-CoreClient
> cd HopeWorks-CoreClient
> git checkout -B main
> git remote set-url origin https://github.com/engrmhk/HopeWorks-CoreClient.git
> git push -u origin main
> ```

## Stack

- Laravel 12, PHP 8.4+
- FilamentPHP v4 (admin UI)
- PostgreSQL, Redis
- Laravel Sanctum (API auth from client instances)

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure PostgreSQL in .env, then:
php artisan migrate --seed
php artisan serve
```

Filament admin panel: `/admin`  
Default super admin: `admin@hopeworks.test` / `password`

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/heartbeat` | Client instances call this to sync license status (Bearer API key) |
| `POST` | `/api/v1/webhooks/stripe` | Payment gateway webhook receiver |

## Running Tests

```bash
php artisan test
```

## Phase 0 Features

- Synod/Church directory CRUD via Filament
- Plan management with module eligibility
- License key issuance (signed JWT)
- Payment enforcement state machine (`active → grace → suspended`)
- Change Affiliation action with audit logging
- Tenant provisioning trigger (stub)
- Global reporting dashboard widget

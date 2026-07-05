# HopeWorks CMS

HopeWorks is a multi-tenant church management platform composed of two Laravel applications:

| Application | Directory | Purpose |
|-------------|-----------|---------|
| **Control Plane** | [`control-plane/`](control-plane/) | Hope Works' internal admin app — manages synods, churches, billing, licensing, and cross-client reporting. There is only ever one instance. |
| **Core Client** | [`core-client/`](core-client/) | The forkable per-client app deployed once per Synod or Independent Church. Tagged releases (`v0.1.0`, etc.) are forked for each client. |

Both apps use **Laravel 12**, **PHP 8.4+**, **FilamentPHP v4**, **PostgreSQL**, and **Redis**.

## Quick Start

### Prerequisites

- PHP 8.4+
- Composer
- PostgreSQL 16+
- Redis

### Control Plane

```bash
cd control-plane
cp .env.example .env   # configure PostgreSQL credentials
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve      # http://localhost:8000
```

Filament admin panel: `/admin`  
Default super admin: `admin@hopeworks.test` / `password`

### Core Client

```bash
cd core-client
cp .env.example .env   # configure PostgreSQL + CONTROL_PLANE_URL
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001
```

Filament admin panel: `/admin`  
Default church admin: `admin@hopeworks.test` / `password`

## Running Tests

```bash
cd control-plane && php artisan test   # 8 tests
cd core-client  && php artisan test   # 18 tests
```

## Architecture Notes

- **Build order:** Control Plane must exist before Core Client instances can sync licenses via `POST /api/v1/heartbeat`.
- **Client customization:** All per-client changes in Core Client go under `app/Custom/` — see [`core-client/CONTRIBUTING.md`](core-client/CONTRIBUTING.md).
- **Modules:** Feature modules live under `app/Modules/{ModuleName}/` with a `module.json` manifest and are gated through `ModuleRegistry`.

## Phase 0 + Phase 1 Status

### Control Plane (Part A)
- [x] Synod/Church directory CRUD via Filament
- [x] Plan management with module eligibility
- [x] License key issuance + `POST /api/v1/heartbeat`
- [x] Payment enforcement state machine (`active → grace → suspended`)
- [x] Change Affiliation action with audit logging
- [x] Tenant provisioning trigger (stub)
- [x] Stripe webhook receiver (stub)
- [x] Global reporting dashboard widget

### Core Client (Part B)
- [x] Module Registry with `@module` directive and route middleware
- [x] Dynamic RBAC via spatie/laravel-permission
- [x] License/heartbeat sync command
- [x] Payment enforcement middleware
- [x] Custom Fields engine
- [x] Multi-level church scoping
- [x] Audit logging
- [x] Authentication, onboarding wizard, branding, admin panel shell

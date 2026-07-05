# HopeWorks Church

The forkable per-client church management application. Deploy one instance per Synod (with multiple churches) or Independent Church. Tagged releases (`v0.1.0`, etc.) are forked for each client.

**Related repo:** [HopeWorks](https://github.com/engrmhk/HopeWorks) — the Control Plane that manages licensing, billing, and the client directory.

## Stack

- Laravel 12, PHP 8.4+
- FilamentPHP v4, Livewire
- PostgreSQL, Redis, Meilisearch (Scout), S3-compatible storage, Laravel Horizon

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure PostgreSQL and CONTROL_PLANE_URL in .env, then:
php artisan migrate --seed
php artisan serve
```

Filament admin panel: `/admin`  
Default church admin: `admin@hopeworks.test` / `password`

## Architecture Rules

- **Client customization** goes under `app/Custom/` only — see [CONTRIBUTING.md](CONTRIBUTING.md)
- **Feature modules** live under `app/Modules/{ModuleName}/` with a `module.json` manifest
- All module gating goes through `ModuleRegistry` — never hardcode `config('modules.*')` checks in core files

## Running Tests

```bash
php artisan test
```

## Phase 0 + Phase 1 Features

- Module Registry with `@module` directive and route middleware
- Dynamic RBAC via spatie/laravel-permission
- License sync command (`hopeworks:sync-license`)
- Payment enforcement middleware (grace banner, read-only, full lock)
- Custom Fields engine
- Multi-level church scoping
- Auth, onboarding wizard, branding, admin panel shell

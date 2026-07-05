# Contributing to HopeWorks Core Client

This document describes how to extend the HopeWorks Core Client application using the module system and client customization namespace.

## Architecture Overview

HopeWorks Core Client is a modular Laravel 12 application with Filament v4 admin panel. Extensions fall into two categories:

1. **Modules** (`app/Modules/`) — first-class feature packages with manifests, routes, and Filament resources
2. **Custom** (`app/Custom/`) — client-specific overrides registered conditionally

## The `app/Custom/` Rule

The `app/Custom/` directory is reserved for **client-specific customizations** that should not ship with the core product:

- Override service bindings
- Add client-only service providers
- Patch or extend core behavior for a single deployment

### Conditional Registration

`CustomServiceProvider` is registered automatically **only if the class exists**:

```php
// app/Providers/AppServiceProvider.php
if (class_exists(\App\Custom\CustomServiceProvider::class)) {
    $this->app->register(\App\Custom\CustomServiceProvider::class);
}
```

### Guidelines

- **Do not** put reusable product features in `app/Custom/` — use modules instead
- **Do not** commit client secrets or credentials in `app/Custom/`
- Keep customizations minimal and well-documented in client-specific README files
- Prefer overriding via service container bindings over modifying core files

## Module Manifest Pattern

Each module lives under `app/Modules/{ModuleName}/` and **must** include a `module.json` manifest at the module root.

### Example: `app/Modules/Attendance/module.json`

```json
{
    "key": "attendance",
    "name": "Attendance",
    "description": "Track and manage attendance records for church services and events.",
    "dependencies": [],
    "permissions": [
        "attendance.view",
        "attendance.manage"
    ]
}
```

### Manifest Fields

| Field | Required | Description |
|-------|----------|-------------|
| `key` | Yes | Unique module identifier used in `church_modules` and middleware |
| `name` | Yes | Human-readable display name |
| `description` | No | Short description for admin UI |
| `dependencies` | No | Array of module keys that must be enabled first |
| `permissions` | No | Permissions auto-created when module is enabled (defaults to `{key}.view` and `{key}.manage`) |

### Module Directory Structure

```
app/Modules/Attendance/
├── module.json
├── Providers/
│   └── AttendanceServiceProvider.php
├── Models/
├── Filament/
│   └── Resources/
├── routes/
│   └── web.php
└── ...
```

### Auto-Discovery

`ModuleServiceProvider` automatically:

1. Scans `app/Modules/*/module.json` and syncs entries to the `modules` table
2. Registers `{ModuleName}ServiceProvider` from `Providers/`
3. Loads `routes/web.php` if present

### Enabling Modules

Modules are enabled per church via `ModuleRegistry`:

```php
app(ModuleRegistry::class)->enableModule('attendance', $churchId);
```

Enabling a module:
- Creates/updates the `church_modules` record
- Auto-generates permissions via `spatie/laravel-permission`

### Route Protection

Protect module routes with the `module.enabled` middleware:

```php
Route::middleware(['web', 'module.enabled:attendance'])
    ->prefix('attendance')
    ->group(function () {
        // ...
    });
```

### Blade Conditional Rendering

Use the `@module` directive to conditionally render UI:

```blade
@module('attendance')
    <div>Attendance widget content</div>
@endmodule
```

### Filament Navigation

Module Filament resources should implement `shouldRegisterNavigation()`:

```php
public static function shouldRegisterNavigation(): bool
{
    return app(ModuleRegistry::class)->isEnabled('attendance');
}
```

## Development Workflow

1. Create module directory under `app/Modules/{ModuleName}/`
2. Add `module.json` with required fields
3. Create `{ModuleName}ServiceProvider` in `Providers/`
4. Add routes, models, Filament resources as needed
5. Run migrations: `php artisan migrate`
6. Enable module for a church via admin or seeder
7. Write feature tests in `tests/Feature/`

## Testing

Tests use SQLite in-memory (`phpunit.xml`). Run:

```bash
php artisan test
```

Key test areas:
- `ModuleRegistryTest` — nav, routes, widgets respect enabled state
- `ModulePermissionTest` — permissions auto-created on enable
- `LicenseEnforcementTest` — subscription enforcement policies
- `ChurchScopeTest` — multi-tenant church isolation
- `CustomFieldTest` — custom field definitions and values

## Environment Variables

| Variable | Description |
|----------|-------------|
| `CONTROL_PLANE_URL` | HopeWorks Control Plane base URL |
| `CONTROL_PLANE_API_KEY` | API key for license heartbeat sync |
| `LICENSE_JWT_SECRET` | JWT verification secret (defaults to `APP_KEY`) |

## License Sync

Sync license status from the Control Plane:

```bash
php artisan hopeworks:sync-license
```

This calls `POST {CONTROL_PLANE_URL}/api/v1/heartbeat` and updates local `license_cache` and `church_modules`.

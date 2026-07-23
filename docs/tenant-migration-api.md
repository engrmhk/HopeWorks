# Tenant Migration API — Control Plane orchestration

Control Plane orchestrator: `App\Services\TenantMigrationService`  
Filament UI: Change Affiliation wizard (gated by `AFFILIATION_CHANGE_ENABLED`)

## Companion endpoints (HopeWorks-church)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `{instance}/api/internal/tenant-export` | Export church bundle (+ `id_map` when available) |
| POST | `{instance}/api/internal/tenant-import` | Import with `commit: false` (dry-run) or `commit: true` (live) |
| POST | `{instance}/api/internal/tenant-archive` | Archive source after successful live migrate |

Auth: `Authorization: Bearer {INTERNAL_SERVICE_SECRET}`

### Dry-run contract

```json
POST /api/internal/tenant-import
{
  "bundle": { … },
  "commit": false
}
```

Expected success body includes:

- `committed: false`
- `row_counts` / `expected_counts`
- `referential_integrity` (or `integrity`) with a real sample check — **not** null/empty/omitted

Dry-run uses a rolled-back transaction on the destination (or scratch DB if Core App is configured for that). Destination production data must not persist.

### Live migrate gate (Control Plane)

1. Wizard runs dry-run and caches a token keyed to `bundle_hash`
2. `migrateChurch()` refuses without that matching token
3. Live import uses `commit: true`, then archives source and updates affiliation
4. Distinct audit actions: `tenant.migration_dry_run_succeeded|failed` vs `tenant.migration_completed`

## Re-enable gate

Do **not** set `AFFILIATION_CHANGE_ENABLED=true` until [JOINT_VERIFICATION_STATUS.md](./JOINT_VERIFICATION_STATUS.md) Steps 0–5 are human-signed.

Related: Core App `docs/tenant-migration-api.md`

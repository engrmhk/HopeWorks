# Joint Verification Checklist — Status (Control Plane)

**Date:** 2026-07-22  
**Repo:** `HopeWorks` (Control Plane)  
**Gate:** Do **not** flip `AFFILIATION_CHANGE_ENABLED=true` until a human completes every checklist step against a live Control Plane + Core App pair.

## Step 0 — Path mismatch

| Item | Finding | Status |
|------|---------|--------|
| Billing portal CP route | `POST /api/v1/billing/portal` in `routes/api.php` | Confirmed |
| Billing portal Core call | `BillingPortalService` → same path (was `/customer-portal`) | Confirmed in Core App |
| Documented contract | `docs/BILLING_PORTAL_API.md` (both repos) | Confirmed |
| Support tickets path | `GET/POST /api/v1/support-tickets` (+ messages) | Confirmed |
| Support ticket docs | Canonical: `docs/SUPPORT_TICKETS_API.md` (both repos). Migration docs stay in `docs/tenant-migration-api.md` — not mixed. | Confirmed |
| Real Stripe portal click | Needs live `STRIPE_SECRET` + suspended church session | **Human only** |

### Step 0 checkboxes (honest)

- [x] Confirmed CP billing path: `/api/v1/billing/portal`
- [x] Confirmed Core App calls the same path
- [x] Paths match in code + docs
- [ ] Manually confirmed “Update Payment Method” opens a real Stripe Customer Portal session
- [x] Support / billing / migration docs separated into clearly named files

**Step 0 is not fully checked off until the human Stripe portal click succeeds.**

## Steps 1–6 — Staging / human only

PHPUnit covers Control Plane dry-run token gating, failed dry-run audit, and commit refusal without a prior dry-run (`TenantMigrationDryRunTest`).

That does **not** replace:

- Seeding a realistic church on Core App (people/households/guardians/attendance morphs)
- Running the Filament Change Affiliation wizard against real instances
- Spot-checking destination row counts and relationships in the UI
- Inspecting `control_plane_audit_logs` for distinct dry-run vs live events

| Step | Agent status |
|------|----------------|
| 1 Test church baseline | Human — seed on Core App / staging |
| 2 Dry run via CP wizard | Human — enable flag only in a locked staging env if testing the wizard |
| 3 Forced failure rollback | Human on staging (Core App PHPUnit covers import rollback) |
| 4 ID collision | Human on staging (Core App PHPUnit covers remapping) |
| 5 Live migration + manual walk | **Human only** |
| 6 Sign-off table | **Human only** |

## Current gate flag

```
AFFILIATION_CHANGE_ENABLED=false   # keep false until Step 6 sign-off
```

Mirror status on Core App: `HopeWorks-church/docs/JOINT_VERIFICATION_STATUS.md`

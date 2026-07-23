# Billing Customer Portal API

Canonical Control Plane path (source of truth: `routes/api.php`):

`POST /api/v1/billing/portal`

| | |
|--|--|
| Auth | `Authorization: Bearer {church instance API key}` |
| Body | `{ "return_url": "https://…" }` (optional) |
| 200 | `{ "url": "https://billing.stripe.com/…" }` |
| 401 | Invalid / missing API key |
| 422 | No Stripe customer yet, LBP currency, or Stripe misconfigured |

Handler: `App\Http\Controllers\Api\BillingPortalController`  
Service: `App\Services\StripeBillingService::createBillingPortalSession()`

Core App consumer: `HopeWorks-church` `BillingPortalService` → suspended page `GET /billing-suspended`.

> Do **not** use `/api/v1/billing/customer-portal` — that path does not exist.

See also: [Support tickets](./SUPPORT_TICKETS_API.md) · [Tenant migration](./tenant-migration-api.md) · [Joint verification](./JOINT_VERIFICATION_STATUS.md)

# Support Tickets API — HopeWorks Control Plane ↔ HopeWorks-church

Auth: `Authorization: Bearer {per-church instance API key}`  
Base: `/api/v1`

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/support-tickets` | List this church's tickets |
| POST | `/support-tickets` | Create ticket + first message |
| GET | `/support-tickets/{id}` | Ticket thread (404 if not owned) |
| POST | `/support-tickets/{id}/messages` | Church reply in thread |

## Create ticket

```json
POST /api/v1/support-tickets
{
  "subject": "Cannot export giving report",
  "body": "Export times out after 30s…",
  "priority": "normal"
}
```

`priority` optional: `low` | `normal` | `high` | `urgent`

**201 response** includes `id`, `subject`, `status`, `priority`, `created_at`, and `messages[]`.

## Thread message shape

```json
{
  "id": 12,
  "body": "…",
  "is_staff": false,
  "created_at": "2026-07-21T12:00:00+00:00"
}
```

## Notifications

- New ticket / church reply → Control Plane emails `SUPPORT_STAFF_NOTIFY_TO`
- Staff Filament reply → Control Plane emails church `contact_email` / `billing_email` / synod contact
- Church UI should poll `GET /support-tickets/{id}` (no instance webhook yet)

See also `App\Http\Controllers\Api\SupportTicketController` docblock.

# Subscription status evaluation

## What actually drives `active → grace → suspended`

**Finding (2026-07-24):** Before this work, there was **no** scheduled job. Transitions only ran when:

1. A church instance called `POST /api/v1/heartbeat` (which called `SubscriptionEnforcementService::evaluate()`), or
2. Payment/dunning/manual-payment paths activated or moved a subscription.

Editing `current_period_end` in admin therefore did nothing until the next client heartbeat.

## Current drivers (all share `evaluate()`)

| Driver | Frequency / trigger |
|--------|---------------------|
| `php artisan subscriptions:evaluate-statuses` | **Every minute** via Laravel scheduler (`routes/console.php`) |
| Church heartbeat | Whenever HopeWorks-church polls |
| Filament **Force Status Check** | On demand for one church |

Scheduler registration:

```php
Schedule::command(EvaluateSubscriptionStatusesCommand::class)->everyMinute();
```

Config mirror: `config('hopeworks.subscription_enforcement')`  
(`schedule_human` = `every minute`, `command` = `subscriptions:evaluate-statuses`)

Production still needs OS cron:

```
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

## Heartbeat push vs license invalidation

Heartbeat is **client-initiated**. The Control Plane cannot push a new license into the Core App.

**Invalidate Cached License** revokes active license JWTs for that church. The instance learns the new status on its **next** heartbeat (or when any remaining local JWT expires). That is the achievable equivalent of “force a fresh license.”

## Timestamps on Church edit (do not conflate)

| Field | Meaning |
|-------|---------|
| `subscriptions.status_evaluated_at` | Last time CP ran the transition check (scheduler / heartbeat / Force Status Check) |
| `churches.last_heartbeat_at` | Last successful `POST /api/v1/heartbeat` from that instance |

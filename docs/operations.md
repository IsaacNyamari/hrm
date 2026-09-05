# Operations runbook

Day-2 operations for the HRM SaaS. See `deployment.md` for first install.

## Common commands

| Task | Command |
|---|---|
| Run the queue (foreground, debugging) | `php artisan queue:work redis -v` |
| Inspect / retry failed jobs | `php artisan queue:failed` · `php artisan queue:retry all` |
| Force the daily jobs now | `php artisan subscriptions:expire` · `php artisan documents:check-expiry` |
| Recompute leave balances for a year | `php artisan leave:accrue --year=2026` |
| Clear & rebuild caches | `php artisan optimize:clear` then `php artisan optimize` |
| Tail logs | `tail -f storage/logs/laravel-$(date +%F).log` |
| Open a REPL against prod data | `php artisan tinker` (read-only mindset) |

`DB::prohibitDestructiveCommands()` is on in production — `migrate:fresh`,
`db:wipe`, `migrate:refresh` are blocked. Roll forward with new migrations only.

## Provisioning a tenant

Tenants self-serve through the registration + subscription flow. To create one
by hand:

```php
$tenant = App\Models\Tenant::create([
    'name' => 'Acme Ltd', 'slug' => 'acme',
    'email' => 'hr@acme.example', 'country' => 'KE',
    'currency' => 'KES', 'timezone' => 'Africa/Nairobi',
    'status' => App\Enums\TenantStatus::Trialing,
]);
app(App\Actions\Tenancy\ProvisionTenant::class)->handle($tenant);   // roles, leave types, payroll, docs
app(App\Services\Subscription\SubscriptionManager::class)->startTrial($tenant, App\Models\Plan::where('slug','starter')->first());
```

Then invite the owner via `UserInvitationController` / the app UI so acceptance
creates the user and assigns `Tenant Owner`.

## Impersonation

Platform operators (`users.is_platform_admin`) can `POST /impersonate/{user}` to
troubleshoot a tenant, and `POST /impersonate/stop` to return. Both transitions
and every action taken while impersonating are written to `audit_logs`
(`via_impersonation = true`). Review with:

```sql
SELECT created_at, event, actor_label, properties
FROM audit_logs WHERE event LIKE 'impersonation.%' ORDER BY id DESC;
```

## Webhooks

* Outbound deliveries are in `webhook_deliveries` (`status` = pending/success/failed).
* An endpoint auto-disables (`is_active = 0`, `disabled_at` set) after 10
  consecutive failures; re-enable it from the tenant's Developers screen or:
  `App\Models\WebhookEndpoint::where('uuid', ...)->update(['is_active' => true, 'failure_count' => 0, 'disabled_at' => null]);`
* Redeliver a failed one by re-queueing: `App\Jobs\DeliverWebhook::dispatch($deliveryId)`.
* Signature scheme (for the receiver): `X-Webhook-Signature: sha256=<hex>` where
  `<hex> = hmac_sha256(timestamp . "." . rawBody, endpoint.secret)` and
  `timestamp = X-Webhook-Timestamp`.

## Billing / M-Pesa

* Daraja STK callbacks hit `POST /api/webhooks/mpesa` and are verified with
  `MPESA_CALLBACK_TOKEN`, then reconciled idempotently
  (`payment_transactions (provider, external_id)` unique).
* A stuck `pending` payment: check `payment_transactions` for the Daraja result,
  then `app(App\Services\Payment\PaymentReconciler::class)` against the payload.
* `subscriptions:expire` lapses unpaid tenants; `billing.*` and `subscription.*`
  routes stay reachable so they can pay and recover.

## Audit log retention

`audit_logs` is append-only and grows unbounded. If you need retention, add a
scheduled prune that **archives then deletes** rows older than N months
(`AuditLog` blocks `delete()` from app code — do it with a raw
`DB::table('audit_logs')->where('created_at','<',...)->delete()` in a dedicated,
reviewed command, after exporting).

## Incident quick-reference

| Symptom | First checks |
|---|---|
| 500s across all tenants | `storage/logs`, `APP_KEY` present, DB/Redis reachable, `config:cache` current |
| One tenant sees another's data | **page immediately** — check `TenantScope` still on the model, `ResolveTenant` in the route group, recent migration; isolation matrix tests in `tests/Feature/Tenancy` |
| Notifications not arriving | queue worker up? `queue:failed`; mail transport; recipient's `notification_preferences` |
| Slow dashboard / org chart | Redis up (they're cached 30–120 s); MySQL slow log; missing index — see `2026_09_05_300000_add_performance_indexes` |
| Webhooks not firing | endpoint `is_active`? subscribed to the event? `webhook_deliveries` status; worker up |

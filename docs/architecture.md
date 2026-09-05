# Architecture

> Status: living document. Sections are expanded as each phase lands.
> Phase 1 (multi-tenant foundation) is implemented; later sections describe the
> target design and are marked _(planned)_ until their phase completes.

## 1. Overview

This is a multi-tenant SaaS Human Resource Management (HRM) platform. Many
organizations ("tenants") subscribe, pay a recurring fee, and manage their own
employees, attendance, leave, payroll, documents and reports in complete
isolation from every other tenant.

| Layer | Choice |
|-------|--------|
| Framework | Laravel 13 (`^13.17`, running 13.30) |
| Language | PHP 8.3+, `declare(strict_types=1)` in new code |
| DB (prod) | MySQL 8+ |
| DB (dev/test) | SQLite |
| Cache / queue / locks | Redis (queue + cache fall back to `database` locally) |
| Auth | Laravel Fortify (session) + Sanctum for API _(planned P16)_ |
| UI | Livewire 4 + Flux 2 + Tailwind 4 + Vite 8 |
| Roles/permissions | `spatie/laravel-permission`, tenant-scoped _(planned P2)_ |
| Media/documents | `spatie/laravel-medialibrary` on a private disk _(planned P10)_ |
| Tests | Pest 4 |
| Static analysis | Larastan / PHPStan level 7 |
| Style | Laravel Pint |

## 2. Tenancy architecture

### 2.1 Model: single database, shared schema, row-level isolation

Every tenant-owned table carries a `tenant_id` foreign key. A global Eloquent
scope adds `where tenant_id = <current tenant>` to every query automatically.
This is the standard approach for a SaaS that must scale to thousands of small
tenants: one schema to migrate, cheap cross-tenant platform analytics, and
straightforward backups. Database-per-tenant was rejected because the product
spec calls for `tenant_id` columns, composite indexes (`tenant_id + column`),
and platform-wide revenue/usage reporting.

Isolation is enforced at four levels, defence-in-depth:

1. **Query** – `App\Models\Scopes\TenantScope` global scope on every
   `BelongsToTenant` model.
2. **Assignment** – the `BelongsToTenant` trait auto-fills `tenant_id` from the
   current context on `creating`, so application code cannot "forget" it.
3. **Authorization** – every policy verifies the acting user and the target
   record share a tenant (`sharesTenant()`), independent of the query scope.
4. **Routing** – `tenant` + `tenant.user` middleware resolve the tenant from the
   request and reject users who do not belong to it before a controller runs.

### 2.2 Tenant context

`App\Support\TenantContext` (bound as a singleton, exposed via the `Tenancy`
facade) holds the current tenant for the lifetime of a request, job, or command.

```
Tenancy::set($tenant)      // bind
Tenancy::get() / ::id()    // read (nullable)
Tenancy::getOrFail()       // read (throws if unbound)
Tenancy::forget()          // clear
Tenancy::run($t, fn)       // bind for a callback, restore after
Tenancy::runWithout(fn)    // clear for a callback (platform / cross-tenant work)
```

When **no** tenant is bound the global scope is a no-op and queries see every
row. That is deliberate: platform-admin screens, queue workers processing
cross-tenant batches, and the scheduler all run without a tenant. Web requests
into tenant routes always have a bound tenant because `ResolveTenant` aborts
otherwise, and `EnsureUserBelongsToTenant` blocks mismatches. Jobs that touch
tenant data must wrap their work in `Tenancy::run()` (see `queue architecture`).

### 2.3 Request lifecycle

```
HTTP request
  → auth (Fortify session)
  → ResolveTenant           resolve by custom domain → subdomain slug → user's tenant; 404 if unresolved
  → EnsureUserBelongsToTenant  403 unless user.tenant_id === tenant.id (platform admins pass)
  → EnsureTenantIsActive    (planned P3) allow billing routes only when subscription lapsed
  → controller / Livewire   queries auto-scoped, policies re-check tenant
```

### 2.4 Tenant resolution

`config/tenancy.php`:

- `central_domains` – hosts that are **not** tenant subdomains (the marketing
  site, `/platform`, `/register`). `app.hrm.test` style.
- `subdomains_enabled` – when true, `abc-company.hrm.test` resolves the
  `abc-company` tenant by `slug`.
- `tenants.custom_domain` column – exact host match, checked first, so a tenant
  can later point `hr.theircompany.com` at the platform (P wiring later; the
  column and lookup exist now).

Central identifiers: every tenant has an immutable `uuid` (ordered UUID, used in
APIs/URLs) and a mutable, unique `slug` (used for subdomains and route-model
binding).

## 3. Authentication & authorization

- **Session auth**: Laravel Fortify (login, registration, email verification,
  password reset, password confirmation, 2FA, passkeys) – shipped by the starter
  kit, retained.
- **User ↔ tenant**: a `User` belongs to at most one tenant. Platform staff have
  `tenant_id = null` and `is_platform_admin = true`. User email is globally
  unique (staff identity is platform-wide); tenant-scoped people live in
  `employees`, whose email is unique per tenant.
- **API auth** _(planned P16)_: Sanctum personal-access tokens, abilities map to
  permissions, tokens carry the tenant.
- **Roles & permissions** _(planned P2)_: `spatie/laravel-permission` with
  `teams = true`, the "team" being the tenant, so a user can hold different
  roles in principle and permissions never leak across tenants. Platform roles
  (Super Admin, Platform Admin, Support Admin, Finance Admin) live in the
  team-less/`null` team.
- **Policies**: one policy per model, auto-discovered. Base rule: acting user
  and subject must share a tenant. Platform admins are denied tenant HR data by
  default and reach it only through audited impersonation _(planned P7)_.

## 4. Database

See [`database.md`](database.md) for the table catalogue and ERD. Conventions:

- `tenant_id` on every tenant-owned table, `FK … on delete cascade`.
- `uuid` (ordered) on anything exposed externally; internal FKs stay `bigint`.
- Composite indexes lead with `tenant_id`: `(tenant_id, employee_number)`,
  `(tenant_id, status)`, `(tenant_id, created_at)`, etc.
- `SoftDeletes` on people/records that must not vanish (employees, departments,
  documents, leave types, users).
- Money stored as integer minor units _(from P3 onward)_; enums as strings via
  PHP backed enums.
- Statutory/config data (tax bands, plan features) lives in tables with
  effective-date ranges, never in code.

## 5. Billing & subscriptions _(planned P3)_

`plans` → `subscriptions` → `invoices` → `invoice_items` → `payments` →
`payment_transactions`. Subscription state machine: `trialing → active →
past_due → (paused|cancelled|expired|suspended)`. `EnsureTenantIsActive`
middleware gates the app when a subscription lapses, always leaving billing,
subscription, account and support routes reachable. Plan limits (employee count,
storage, features) go through a single `UsageLimit`/`FeatureGate` service rather
than scattered `if plan === …` checks.

## 6. Payments _(planned P3/P12)_

`App\Contracts\PaymentGateway` — `initiatePayment / verifyPayment /
queryTransaction / refund / handleWebhook`. First implementation
`MpesaPaymentGateway`; Airtel Money, Pesapal, card and bank slot in behind the
same interface. Rules: success is only ever confirmed server-side (verify or
signed webhook), provider transaction IDs are stored, webhook handlers are
idempotent (unique constraint on `payment_transactions.external_id`), signatures
verified before processing.

## 7. Payroll _(planned P8)_

Configurable salary structures composed of components (earnings/deductions),
each fixed / percentage / formula, flagged taxable/pensionable/recurring. A
**safe** expression evaluator (whitelisted variables and operators — never
`eval`) computes formula components. Kenyan statutory items (PAYE, NSSF, SHIF,
Housing Levy) are rows in `tax_rules` with `effective_from/effective_to` +
JSON config, so rate changes need no code. Payroll runs are transactional and
lock on approval.

## 8. Queues _(Redis; workers via Supervisor in prod)_

Priorities `high, default, low`. Queued work: mail, PDF generation (payslips,
letters), payroll runs, report exports, notifications, imports, subscription
and document-expiry reminders. Any job touching tenant data resolves and binds
its tenant (`Tenancy::run($tenant, …)`) as the first step; a `TenantAware` job
base class _(planned P4)_ serialises `tenant_id` and does this automatically.

## 9. Notifications _(planned P15)_

Laravel Notifications over `mail` + `database` channels (SMS/WhatsApp later),
all queued. Domain events (`LeaveRequested`, `PayrollProcessed`,
`DocumentExpiring`, `SubscriptionExpiring`, `PaymentFailed`, …) fan out to
listeners that notify the right recipients. Mail uses per-tenant branding
(logo, colours, footer).

## 10. API _(planned P16)_

`/api/v1`, Sanctum-authenticated, Laravel API Resources for every payload,
tenant resolved from the token and enforced by the same scope + policies as web.
Webhooks under `/api/webhooks/{provider}` with signature verification and
idempotency.

## 11. Security model

- Never trust `tenant_id`, `employee_id`, role, or payment status from the
  client — all derived server-side from the authenticated user / bound tenant.
- CSRF on all web forms; output escaping by default in Blade; parameterised
  queries only; mass-assignment guarded (`$guarded = ['id']` + explicit fills
  for sensitive columns).
- Private disk for all documents; downloads go through a controller + policy +
  (optionally) signed URLs. File paths namespaced by tenant UUID so paths are
  unguessable and unshareable.
- Rate limiting on auth and API; account lockout via Fortify.
- Immutable audit log _(planned P17)_ — append-only, not editable by tenant
  users — records who/what/when/old/new/IP/UA for every sensitive action.
- Secrets only in `.env`; nothing sensitive logged.
- Every tenant-specific cache key is prefixed `tenant:{id}:…`.

## 12. Deployment _(planned P20)_

Ubuntu + Nginx + PHP-FPM + MySQL + Redis + Supervisor (queue workers) + cron
(`schedule:run` every minute) + SSL. Details in `docs/deployment.md`.

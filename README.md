# HRM SaaS

A production-grade, multi-tenant Human Resource Management platform built on
Laravel 13 (PHP 8.3+, MySQL 8, Redis, Livewire, Sanctum). One codebase serves
many organisations with strict row-level isolation at the query, authorization,
routing and cache layers.

> The repository directory is named `real-estate` for historical reasons — the
> application is HRM.

## Modules

| Area | What it does |
|---|---|
| **Tenancy & auth** | subdomain / custom-domain tenant resolution, Spatie permissions in teams mode, invitations, 2FA + passkeys, platform operators |
| **Billing** | plans, trials, subscriptions, invoices, M-Pesa (Daraja) + manual gateways, plan limits & feature gates |
| **People** | full employee profiles, emergency contacts / bank / statutory (encrypted), import & export, branches / departments / designations, org chart |
| **Time** | attendance with shifts, geofencing and device webhooks; leave types, multi-level approval workflow, balances, holidays, calendar |
| **Payroll** | salary structures & components, a safe formula engine (no `eval`), effective-dated statutory rules (PAYE / NSSF / SHIF / Housing Levy), runs, payslips, loans |
| **Self-service** | employee portal, documents, HR requests, announcements |
| **Documents** | HR letter templates (safe placeholder rendering), document types, expiry reminders |
| **Performance** | review cycles, goals, KPIs, competency reviews, weighted scoring |
| **Recruitment / ATS** | job positions, vacancies, a per-vacancy pipeline, applicants, interviews, offers, offer→employee conversion, public careers board |
| **Finance** | expense claims with approval chain & mileage, company asset register, loan self-service |
| **Reporting** | seven cross-module report builders with Excel / CSV / print export, permission-gated dashboard metrics |
| **Notifications** | in-app notification centre, per-category e-mail preferences, per-tenant e-mail branding |
| **API v1** | Sanctum personal-access tokens with abilities + rate limits; signed outbound webhooks |
| **Security** | append-only audit trail, platform impersonation with banner + audit, hardening headers |

## Local development

Requires PHP 8.3+, Composer, Node 20+, and MySQL **or** SQLite.

```bash
composer install
npm install && npm run build      # or: npm run dev
cp .env.example .env
php artisan key:generate
php artisan migrate --seed         # seeds two demo orgs (see below)
php artisan serve
```

Demo logins after `--seed` (`DemoTenantSeeder`):

| Org | URL | Owner | Password |
|---|---|---|---|
| ABC Company (trial) | `abc-company.localhost:8000` | `owner@abc-company.test` | `password` |
| XYZ Limited (paid) | `xyz-limited.localhost:8000` | `owner@xyz-limited.test` | `password` |

Add `127.0.0.1 abc-company.localhost xyz-limited.localhost` to your hosts file, or
just visit `localhost:8000` — an authenticated user's own tenant is resolved when
the host names no tenant.

## Quality gate

```bash
composer test        # Pint (style) + PHPStan level 7 + Pest
```

The suite is **413 tests / 1070 assertions**, run against SQLite `:memory:`.
Every module has feature *and* tenant-isolation coverage; `tests/Feature/Tenancy`
holds the cross-module isolation, global-scope and authorization matrices.

## Documentation

| Doc | Contents |
|---|---|
| [`docs/architecture.md`](docs/architecture.md) | tenancy model, request lifecycle, key patterns |
| [`docs/database.md`](docs/database.md) | schema, per-phase table map, ERD |
| [`docs/development-plan.md`](docs/development-plan.md) | the 20-phase build log + decisions |
| [`docs/deployment.md`](docs/deployment.md) | production install, DNS/TLS, queue, scheduler, checklist |
| [`docs/operations.md`](docs/operations.md) | day-2 runbook, incident quick-reference |

## Conventions

`declare(strict_types=1)` everywhere · `$guarded = ['id']` · FormRequest / Policy /
Service-or-Action split · money as integer minor units · enums for every status ·
queued notifications & event subscribers · statutory rules are effective-dated
data, never hard-coded · private disk for sensitive files, always behind an
authorising controller.
